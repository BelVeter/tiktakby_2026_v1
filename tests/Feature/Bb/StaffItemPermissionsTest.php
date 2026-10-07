<?php

namespace Tests\Feature\Bb;

use bb\classes\Permission;
use bb\models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Права сотрудника на товары: тарифы (7), выбытие (8), каталог (9).
 * Раньше это держалось на зашитом `getId()==26` (Юля).
 *
 * Механика прав проверяется на временном пользователе внутри транзакции
 * `\bb\Db` (откатывается). Миграция проверяется через Laravel DB в своей
 * транзакции — она идемпотентна, поэтому повторный up() ничего не меняет.
 *
 * Требует применённой миграции 2026_10_07_120000_seed_staff_item_permissions
 * (для тестов миграции; механика прав от неё не зависит — берёт право 1).
 */
class StaffItemPermissionsTest extends TestCase
{
    private const MIGRATION = 'database/migrations/2026_10_07_120000_seed_staff_item_permissions.php';

    protected function setUp(): void
    {
        parent::setUp();
        User::$_users = [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        User::$_users = [];
        parent::tearDown();
    }

    private function conn(): \mysqli
    {
        return \bb\Db::getInstance()->getConnection();
    }

    /** Временный сотрудник level 0 без прав; возвращает его logpass_id. */
    private function createTempStaff(): int
    {
        $login = 'tmp_perm_' . bin2hex(random_bytes(4));
        $ok = $this->conn()->query(
            "INSERT INTO logpass SET log='$login', pass='x', lp_fio='tmp', `level`=0, delivery=0, time_yn=0, "
            . "time_from=0, time_to=0, ip_yn=0, ip_addr='', ip_addr_2='', ip_addr_3='', zp_yn=0, oklad=0, "
            . "`active`=0, main_role='consultant', color=''"
        );
        $this->assertTrue((bool) $ok, 'не удалось создать временного сотрудника: ' . $this->conn()->error);

        return (int) $this->conn()->insert_id;
    }

    // ─── коды ─────────────────────────────────────────────────────────────────

    public function test_permission_codes_match_the_spec(): void
    {
        $this->assertSame(7, Permission::TARIFFS);
        $this->assertSame(8, Permission::DISPOSAL);
        $this->assertSame(9, Permission::CATALOG);
    }

    // ─── механика ─────────────────────────────────────────────────────────────

    public function test_denied_without_session(): void
    {
        $this->assertFalse(User::currentHasPermission(Permission::TARIFFS));
    }

    public function test_staff_passes_only_with_the_granted_permission(): void
    {
        \bb\Db::startTransaction();
        try {
            $id = $this->createTempStaff();
            $_SESSION['user_id'] = $id;

            $this->assertFalse(User::currentHasPermission(1), 'без строки в user_permissions права нет');

            $this->conn()->query("INSERT INTO user_permissions SET user_id=$id, permission_id=1");
            User::$_users = []; // права кэшируются в объекте пользователя

            $this->assertTrue(User::currentHasPermission(1), 'выданное право действует');
            $this->assertFalse(User::currentHasPermission(2), 'чужое право не действует');
        } finally {
            \bb\Db::rollBackTransaction();
        }
    }

    public function test_owner_passes_without_rows(): void
    {
        $exists = $this->conn()->query("SELECT 1 FROM logpass WHERE logpass_id=3")->num_rows > 0;
        if (!$exists) {
            $this->markTestSkipped('в локальной БД нет владельца id 3');
        }
        $_SESSION['user_id'] = 3;

        $this->assertTrue(User::currentHasPermission(Permission::TARIFFS));
        $this->assertTrue(User::currentHasPermission(Permission::DISPOSAL));
        $this->assertTrue(User::currentHasPermission(Permission::CATALOG));
    }

    // ─── миграция ─────────────────────────────────────────────────────────────

    public function test_migration_grants_all_three_to_katya_and_yulia_and_is_idempotent(): void
    {
        require_once base_path(self::MIGRATION);

        DB::beginTransaction();
        try {
            (new \SeedStaffItemPermissions())->up();
            (new \SeedStaffItemPermissions())->up(); // повтор не должен ничего задвоить

            foreach ([7, 8, 9] as $code) {
                $this->assertSame(
                    1,
                    DB::table('permissions')->where('int_code', $code)->count(),
                    "право $code должно быть заведено ровно один раз"
                );
            }

            foreach ([22, 26] as $userId) {
                if (!DB::table('logpass')->where('logpass_id', $userId)->exists()) {
                    continue; // чистая БД без этого сотрудника
                }
                foreach ([7, 8, 9] as $code) {
                    $this->assertSame(
                        1,
                        DB::table('user_permissions')
                            ->where(['user_id' => $userId, 'permission_id' => $code])->count(),
                        "у сотрудника $userId право $code должно быть выдано ровно один раз"
                    );
                }
            }
        } finally {
            DB::rollBack();
        }
    }

    // ─── гарды на исходники ───────────────────────────────────────────────────

    public function test_no_hardcoded_staff_id_26_left_in_live_bb_pages(): void
    {
        $root = dirname(__DIR__, 3);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root . '/bb', \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $offenders = [];
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $path = str_replace($root . '/', '', $file->getPathname());
            // phpqrcode — сторонняя библиотека; kr_baza_new3.php — мёртвая старая копия списка товаров.
            if (strpos($path, 'bb/phpqrcode/') === 0 || $path === 'bb/kr_baza_new3.php') {
                continue;
            }
            if (preg_match('/(getId\(\)|id_user)\s*==\s*26\b/', file_get_contents($file->getPathname()))) {
                $offenders[] = $path;
            }
        }

        $this->assertSame([], $offenders, 'id сотрудника 26 нельзя зашивать в код — используйте права');
    }

    public function test_pages_are_gated_by_the_permission_constants(): void
    {
        $root = dirname(__DIR__, 3);
        $expect = [
            'bb/rent_tarifs.php'   => ['Permission::TARIFFS'],
            'bb/tovar_new.php'     => ['requireCurrentPermission(\bb\classes\Permission::CATALOG'],
            'bb/tovar_new_mod.php' => ['requireCurrentPermission(\bb\classes\Permission::CATALOG'],
            'bb/tovar_del.php'     => ['requireCurrentPermission(\bb\classes\Permission::DISPOSAL'],
            'bb/kr_baza_new.php'   => ['Permission::TARIFFS', 'Permission::DISPOSAL', 'Permission::CATALOG'],
            'bb/index.php'         => ['Permission::TARIFFS', 'Permission::CATALOG'],
        ];

        foreach ($expect as $path => $needles) {
            $code = file_get_contents($root . '/' . $path);
            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $code, "$path должен проверять право: $needle");
            }
        }
    }
}
