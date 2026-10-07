<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Права сотрудника на товары (docs/superpowers/specs/2026-10-07-staff-item-permissions-design.md):
 * 7 — тарифы, 8 — выбытие, 9 — каталог. Раньше всё это держалось на зашитом
 * `getId()==26` (Юля); выдаём права Кате (22) и Юле (26), чтобы доступ Юли
 * не изменился до её ухода.
 *
 * Идемпотентна: permissions.int_code уникален, user_permissions проверяется
 * перед вставкой. Сотрудника, которого нет в logpass (чистая БД), пропускаем —
 * иначе упадёт внешний ключ user_list_constrain.
 */
class SeedStaffItemPermissions extends Migration
{
    private const PERMISSIONS = [
        7 => 'Тарифы товаров',
        8 => 'Выбытие товара',
        9 => 'Каталог: добавление и правка товаров и моделей',
    ];

    private const GRANTED_TO = [22, 26];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $code => $description) {
            if (!DB::table('permissions')->where('int_code', $code)->exists()) {
                DB::table('permissions')->insert(['int_code' => $code, 'description' => $description]);
            }
        }

        foreach (self::GRANTED_TO as $userId) {
            if (!DB::table('logpass')->where('logpass_id', $userId)->exists()) {
                continue;
            }
            foreach (array_keys(self::PERMISSIONS) as $code) {
                $granted = DB::table('user_permissions')
                    ->where(['user_id' => $userId, 'permission_id' => $code])
                    ->exists();
                if (!$granted) {
                    DB::table('user_permissions')->insert(['user_id' => $userId, 'permission_id' => $code]);
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('user_permissions')->whereIn('permission_id', array_keys(self::PERMISSIONS))->delete();
        DB::table('permissions')->whereIn('int_code', array_keys(self::PERMISSIONS))->delete();
    }
}
