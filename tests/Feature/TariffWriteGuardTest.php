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
}
