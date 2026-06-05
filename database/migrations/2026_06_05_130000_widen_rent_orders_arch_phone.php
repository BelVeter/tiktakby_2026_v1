<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * rent_orders_arch.phone was int(11) while rent_orders.phone is bigint(15).
 * Full Belarusian numbers (e.g. 375291234567) overflow int(11) and were silently
 * stored as 2147483647 (INT_MAX) on archival — ~11.8k rows affected historically.
 * Widen to bigint(15) to match the active table and stop future data loss
 * (also makes dedup-against-archive by phone work for full numbers going forward).
 * NOTE: already-overflowed historical values cannot be recovered.
 */
class WidenRentOrdersArchPhone extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE rent_orders_arch MODIFY phone BIGINT(15) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE rent_orders_arch MODIFY phone INT(11) NOT NULL");
    }
}
