<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Захват истории тарифов сделан на уровне кода, а не триггеров БД, поэтому
 * любой сырой INSERT/UPDATE/DELETE по rent_tarif_act мимо bb/classes/Tariff.php
 * пройдёт мимо журнала. Этот тест ловит такие места.
 *
 * Исключения:
 *   - bb/classes/Tariff.php — единственная легальная точка записи;
 *   - database/migrations/  — разовые миграции каталога правят таблицу
 *     напрямую по своей природе (слияние и чистка моделей).
 */
class TariffWriteGuardTest extends TestCase
{
    public function test_no_raw_tariff_writes_outside_tariff_class(): void
    {
        $root = dirname(__DIR__, 2);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $offenders = [];
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $path = str_replace($root . '/', '', $file->getPathname());

            if (strpos($path, 'vendor/') === 0
                || strpos($path, 'node_modules/') === 0
                || strpos($path, 'storage/') === 0
                || strpos($path, 'tests/') === 0
                || strpos($path, 'database/migrations/') === 0
                || $path === 'bb/classes/Tariff.php') {
                continue;
            }

            $code = file_get_contents($file->getPathname());
            if (preg_match('/(INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+`?rent_tarif_act`?/i', $code)) {
                $offenders[] = $path;
            }
        }

        $this->assertSame([], $offenders,
            'сырые DML по rent_tarif_act должны идти только через bb/classes/Tariff.php');
    }

    /**
     * bb/ не использует composer autoload на проде: каждая страница подключает
     * нужные классы явными require_once, и PHPUnit (который стартует через
     * Laravel с composer autoload) этого не ловит. Tariff.php жёстко зависит
     * от TariffHistory (вызывает TariffHistory::log(...)), но ни одна из шести
     * страниц, подключающих Tariff.php (bb/rent_tarifs.php, bb/kr_baza_new.php,
     * bb/kr_baza_new3.php, bb/bulk_actions.php, bb/scanner_tovar.php,
     * "bb/scanner_tovar copy.php"), не подключает TariffHistory.php напрямую.
     * Поэтому зависимость обязана ехать вместе с самим классом.
     *
     * Тест читает исходник файла как текст (а не полагается на автозагрузку),
     * поэтому ловит именно "легаси-режим без composer autoload".
     */
    public function test_tariff_class_requires_tariff_history_itself(): void
    {
        $path = dirname(__DIR__, 2) . '/bb/classes/Tariff.php';
        $code = file_get_contents($path);

        $this->assertMatchesRegularExpression(
            '/require_once\s*\(?\s*__DIR__\s*\.\s*[\'"]\/TariffHistory\.php[\'"]\s*\)?\s*;/',
            $code,
            'bb/classes/Tariff.php должен сам подключать TariffHistory.php через require_once __DIR__ ' .
            '— иначе легаси-страницы (bb/rent_tarifs.php и другие), которые не используют composer autoload, ' .
            'упадут с "Class bb\\classes\\TariffHistory not found" при обращении к Tariff::saveNew()/update()/delete().'
        );
    }
}
