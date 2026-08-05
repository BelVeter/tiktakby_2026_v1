<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class BackfillHistory extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. Backfill is_internal
        DB::statement("
            UPDATE a1_call_analysis ca
            JOIN a1_call_recordings r ON r.uuid = ca.recording_uuid
            SET ca.is_internal = 1
            WHERE r.caller_part IN ('375296303532','375296303558','375296309994')
              AND r.callee_part IN ('375296303532','375296303558','375296309994')
        ");

        // 2. Backfill ai_result prefixes
        DB::statement("
            UPDATE a1_call_analysis
            SET ai_result = 'extension'
            WHERE ai_result = 'booking'
              AND ai_result_detail LIKE 'Продление:%'
        ");

        DB::statement("
            UPDATE a1_call_analysis
            SET ai_result = 'service'
            WHERE ai_result = 'info'
              AND ai_result_detail LIKE 'Обслуживание:%'
        ");

        DB::statement("
            UPDATE a1_call_analysis
            SET ai_result = 'missed'
            WHERE ai_result = 'info'
              AND ai_result_detail LIKE 'Упущено%'
        ");

        // 3. Backfill missed_reason
        DB::statement("
            UPDATE a1_call_analysis
            SET missed_reason = 'stock'
            WHERE missed_item LIKE 'нет в наличии:%'
              AND missed_reason IS NULL
        ");

        DB::statement("
            UPDATE a1_call_analysis
            SET missed_reason = 'assortment'
            WHERE missed_item LIKE 'нет в ассортименте:%'
              AND missed_reason IS NULL
        ");

        // 4. Backfill missed_outcome
        DB::statement("
            UPDATE a1_call_analysis
            SET missed_outcome = 'hard'
            WHERE ai_business_note LIKE '%отказ)%'
              AND missed_outcome IS NULL
        ");

        DB::statement("
            UPDATE a1_call_analysis
            SET missed_outcome = 'soft'
            WHERE ai_business_note LIKE '%готов ждать)%'
              AND missed_outcome IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Reverse operations are inherently lossy here, so left empty or partially reversed
        // if absolutely necessary. We will not implement down() for data migrations.
    }
}
