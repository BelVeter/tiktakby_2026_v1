<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Сводит написания производителей к одному варианту.
 *
 * ЗАЧЕМ. Производитель — свободная строка в `tovar_rent.producer`, и логотип
 * бренда подтягивается по ТОЧНОМУ совпадению этой строки:
 * `Model::getModelIdsArrayByProducer()` делает `WHERE producer='...'`, а на нём
 * стоят `ModelWeb::loadLastProducerLogo()` (подставить логотип бренда новой
 * странице) и `updateLogoUrlForAll()` (разослать логотип всем страницам бренда).
 * Любой лишний вариант написания отрезает модели от логотипа их же бренда.
 * Producer — ещё и публичная сущность: карточки на главной
 * (`Producer::getAllProducersTovExists()`) и индексируемый лендинг
 * `/ru/producer?producer=...` с self-canonical.
 *
 * ЧТО ВАЖНО ПРО КОЛЛАЦИЮ. `utf8mb3_general_ci` игнорирует регистр И хвостовые
 * пробелы (PAD SPACE): `WHERE producer='Chicco'` уже возвращает и `'Chicco '`
 * (78 строк на проде), `'THULE'` уже находит и `'Thule'`. Поэтому варианты по
 * регистру и хвостовому пробелу на поиск и на логотипы НЕ влияют — это
 * косметика в выпадашках и отчётах с `GROUP BY BINARY`. А вот ВЕДУЩИЙ пробел
 * коллация не игнорирует: `' Simple Parenting'` — отдельное значение, отдельная
 * карточка на главной и отдельный лендинг.
 * Следствие для самой миграции: `WHERE producer <> TRIM(producer)` не найдёт
 * строку с хвостовым пробелом — сравнение их не различает. Поэтому TRIM
 * применяется безусловно (docs/db_notes.md).
 *
 * ПЕРЕИМЕНОВАНИЯ, МЕНЯЮЩИЕ URL. `CheckRedirects` матчит только путь, без
 * query-строки, поэтому `?producer=<старое>` через модуль редиректов не
 * перенаправить. Для двух реальных переименований 301 отдаёт сам
 * `SearchController::producerFilter()` по карте синонимов.
 */
class NormalizeProducerSpellings extends Migration
{
    private const TABLES = ['tovar_rent', 'tovar_rent_items', 'tovar_rent_items_arch'];

    /**
     * Каноничное написание => варианты, которые к нему сводим (уже после TRIM).
     *
     * Регистр (побайтово разные, для поиска эквивалентны):
     *   Chi lok BO (5 моделей) / Chi Lok Bo (1)
     *   Medela (1) / MEDELA (1)   — берём нормальное написание бренда
     *   Thule (1) / THULE (1)     — то же
     *
     * Разные строки (для поиска НЕ эквивалентны):
     *   I love mum (5) / I love mum, РФ (11) — «, РФ» это пометка о стране,
     *     а не часть бренда. У всех 16 моделей ноль живых единиц (только архив)
     *     и ни одной веб-страницы, поэтому на сайте слияние не видно вообще.
     *   Simple Parenting (3, был с ведущим пробелом) / Simple Parenting Doona (3)
     *     — одна компания: Simple Parenting выпускает линейку Doona. В первой
     *     группе автокресла-люльки «Doona», «Doona X», «База Doona», во второй
     *     велосипеды «Liki Trike S1». Каноном берём `Simple Parenting Doona`:
     *     токен «Doona» уже стоит во всех title и слагах обеих групп и именно
     *     его ищут, а у этой группы больше живых единиц (11 против 4).
     */
    private const CANONICAL = [
        'Chi lok BO'             => ['Chi Lok Bo'],
        'Medela'                 => ['MEDELA'],
        'Thule'                  => ['THULE'],
        'I love mum'             => ['I love mum, РФ'],
        'Simple Parenting Doona' => ['Simple Parenting'],
    ];

    public function up(): void
    {
        $stats = [];

        // 1. Хвостовые и ведущие пробелы. Безусловно: условие с TRIM не сработает,
        //    коллация PAD SPACE не отличает 'Chicco ' от 'Chicco'.
        foreach (self::TABLES as $table) {
            $stats["trim:{$table}"] = DB::table($table)
                ->update(['producer' => DB::raw('TRIM(producer)')]);
        }

        // 2. Сведение вариантов. BINARY — чтобы не переписывать строки, которые
        //    и так каноничны (иначе ci-сравнение зацепило бы их тоже).
        foreach (self::CANONICAL as $canonical => $variants) {
            foreach ($variants as $variant) {
                foreach (self::TABLES as $table) {
                    $changed = DB::table($table)
                        ->whereRaw('BINARY producer = ?', [$variant])
                        ->update(['producer' => $canonical]);

                    if ($changed > 0) {
                        $stats["{$variant} -> {$canonical} ({$table})"] = $changed;
                    }
                }
            }
        }

        // Карточки производителей на главной кэшируются на сутки и сами
        // не инвалидируются — сбрасываем, иначе там останутся старые имена.
        Cache::forget('all_producers_tov_exists');

        logger()->info('NormalizeProducerSpellings: выполнено', $stats + [
            'variants_left' => $this->countRemainingVariants(),
        ]);
    }

    /**
     * Сколько групп «одно и то же написание в разных байтах» осталось.
     * Ноль означает, что каждый бренд представлен ровно одной строкой.
     */
    private function countRemainingVariants(): int
    {
        return DB::table(DB::raw('(SELECT LOWER(TRIM(producer)) norm, COUNT(DISTINCT BINARY producer) v
                                  FROM tovar_rent GROUP BY LOWER(TRIM(producer)) HAVING v > 1) z'))
            ->count();
    }

    public function down(): void
    {
        // Необратимо: восстановление — из дампа, снятого перед выкладкой.
    }
}
