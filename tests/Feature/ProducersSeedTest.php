<?php

namespace Tests\Feature;

use bb\classes\Similarity;
use bb\Db;
use Tests\TestCase;

class ProducersSeedTest extends TestCase
{
    public function test_seed_covers_every_distinct_producer_value(): void
    {
        $mysqli = Db::getInstance()->getConnection();

        $expected = $mysqli->query("
            SELECT DISTINCT producer FROM (
                SELECT producer FROM tovar_rent WHERE producer <> ''
                UNION SELECT producer FROM tovar_rent_items WHERE producer <> ''
                UNION SELECT producer FROM tovar_rent_items_arch WHERE producer <> ''
            ) u
        ")->fetch_all();
        $expectedNames = array_column($expected, 0);

        $seeded = $mysqli->query('SELECT name FROM producers')->fetch_all();
        $seededNames = array_column($seeded, 0);

        sort($expectedNames);
        sort($seededNames);
        $this->assertSame($expectedNames, $seededNames);
    }

    public function test_seed_is_idempotent(): void
    {
        // Laravel грузит файлы миграций через require при `artisan migrate`,
        // а не через composer autoload по имени класса — в отдельном
        // процессе PHPUnit класс ещё не подключён.
        require_once database_path('migrations/2026_08_15_000002_seed_producers_table.php');

        $mysqli = Db::getInstance()->getConnection();
        $before = $mysqli->query('SELECT COUNT(*) n FROM producers')->fetch_assoc()['n'];

        (new \SeedProducersTable())->up();

        $after = $mysqli->query('SELECT COUNT(*) n FROM producers')->fetch_assoc()['n'];
        $this->assertSame($before, $after);
    }

    public function test_seed_gates_known_non_brands(): void
    {
        $mysqli = Db::getInstance()->getConnection();

        foreach (['РБ', 'РФ', 'Польша', 'вечернее', '-'] as $value) {
            $row = $mysqli->query("SELECT is_active FROM producers WHERE name='" . addslashes($value) . "'")->fetch_assoc();
            if ($row === null) {
                continue; // значения могло не быть в текущем снимке данных
            }
            $this->assertSame(0, (int) $row['is_active'], "«{$value}» должен быть скрыт (is_active=0)");
        }
    }

    public function test_seed_carries_logo_from_max_over_producer_models(): void
    {
        $mysqli = Db::getInstance()->getConnection();

        $row = $mysqli->query("
            SELECT tr.producer, MAX(NULLIF(w.logo, '')) AS logo
            FROM tovar_rent tr
            JOIN rent_model_web w ON w.model_id = tr.tovar_rent_id
            WHERE w.logo <> ''
            GROUP BY tr.producer
            LIMIT 1
        ")->fetch_assoc();

        if ($row === null) {
            $this->markTestSkipped('нет ни одной модели с логотипом в текущем снимке данных');
        }

        $seededLogo = $mysqli->query("SELECT logo FROM producers WHERE name='" . addslashes($row['producer']) . "'")
            ->fetch_assoc()['logo'];

        $this->assertSame($row['logo'], $seededLogo);
    }

    public function test_name_norm_matches_similarity_normalize(): void
    {
        $mysqli = Db::getInstance()->getConnection();
        $row = $mysqli->query('SELECT name, name_norm FROM producers LIMIT 1')->fetch_assoc();

        if ($row === null) {
            $this->markTestSkipped('справочник пуст');
        }

        $this->assertSame(Similarity::normalize($row['name']), $row['name_norm']);
    }
}
