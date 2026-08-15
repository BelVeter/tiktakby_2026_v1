<?php

use bb\classes\Similarity;
use bb\Db;
use Illuminate\Database\Migrations\Migration;

/**
 * Засевает producers из ВСЕХ существующих значений producer — без
 * автослияния (это отдельный шаг, детектор дублей только предупреждает,
 * решает человек — см. спеку). Источник — объединение трёх таблиц, а не
 * только tovar_rent: архивные строки не всегда совпадают с живыми моделями
 * 1-в-1, а терять значение при засеве нельзя (иначе заливка логотипа для
 * такого бренда молча уйдёт только в старую rent_model_web.logo и никогда
 * не попадёт в справочник).
 *
 * Список гашения — ТОЛЬКО значения, уже названные не-брендами в одобренной
 * спеке (2026-08-14-producers-directory-design.md, раздел «Поле используется
 * не только под бренд»). Список для ПРОДА владелец подтверждает отдельно
 * перед деплоем (docs/prod_pending.md) — он может отличаться от локального.
 */
class SeedProducersTable extends Migration
{
    private const GATED_NON_BRANDS = ['РБ', 'РФ', 'Польша', 'вечернее', '-'];

    public function up()
    {
        $mysqli = Db::getInstance()->getConnection();

        $names = $mysqli->query("
            SELECT DISTINCT producer FROM (
                SELECT producer FROM tovar_rent WHERE producer <> ''
                UNION SELECT producer FROM tovar_rent_items WHERE producer <> ''
                UNION SELECT producer FROM tovar_rent_items_arch WHERE producer <> ''
            ) u
        ")->fetch_all();

        $now = time();

        foreach ($names as $row) {
            $name = $row[0];
            $escaped = addslashes($name);

            $exists = $mysqli->query("SELECT producer_id FROM producers WHERE name='$escaped'")->fetch_assoc();
            if ($exists) {
                continue; // идемпотентность: повторный прогон ничего не дублирует
            }

            $logoRow = $mysqli->query("
                SELECT MAX(NULLIF(w.logo, '')) AS logo
                FROM tovar_rent tr
                JOIN rent_model_web w ON w.model_id = tr.tovar_rent_id
                WHERE tr.producer = '$escaped'
            ")->fetch_assoc();
            $logo = $logoRow && $logoRow['logo'] !== null ? $logoRow['logo'] : '';

            $isActive = in_array($name, self::GATED_NON_BRANDS, true) ? 0 : 1;
            $nameNorm = addslashes(Similarity::normalize($name));
            $logoEscaped = addslashes($logo);

            $mysqli->query("
                INSERT INTO producers SET
                    name='$escaped', name_norm='$nameNorm', logo='$logoEscaped',
                    is_active=$isActive, cr_time=$now
            ");
        }
    }

    public function down()
    {
        Db::getInstance()->getConnection()->query('TRUNCATE TABLE producers');
    }
}
