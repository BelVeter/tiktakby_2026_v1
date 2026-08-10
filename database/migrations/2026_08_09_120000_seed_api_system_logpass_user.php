<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds a dedicated "API" logpass row used as cr_who_id for every doh_rash
 * row written by POST /finance/bank-import — so bank-imported rows are
 * attributed to a real, inert account instead of a guessed employee id.
 * active=0 means it can never log in (bb/models/User's login query requires
 * active>0), so the random `pass` value has no credential to protect.
 */
class SeedApiSystemLogpassUser extends Migration
{
    public function up(): void
    {
        DB::table('logpass')->updateOrInsert(
            ['log' => 'api_system'],
            [
                'pass'      => bin2hex(random_bytes(16)),
                'lp_fio'    => 'API',
                'level'     => -1,
                'delivery'  => 0,
                'time_yn'   => 0,
                'time_from' => 0,
                'time_to'   => 0,
                'ip_yn'     => 0,
                'ip_addr'   => '',
                'ip_addr_2' => '',
                'ip_addr_3' => '',
                'zp_yn'     => 0,
                'oklad'     => 0,
                'active'    => 0,
                'main_role' => 'consultant',
                'color'     => '',
            ]
        );
    }

    public function down(): void
    {
        DB::table('logpass')->where('log', 'api_system')->delete();
    }
}
