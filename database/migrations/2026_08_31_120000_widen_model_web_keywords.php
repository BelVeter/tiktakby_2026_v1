<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Расширяет rent_model_web.keywords с VARCHAR(256) до VARCHAR(512).
 *
 * Зачем: при ревью keywords по всему живому каталогу (789 моделей, см.
 * search-overhaul) 65 записей (8%) упирались ровно в старый лимит 256 и
 * обрывались посреди слова — владелец пишет keywords многословными фразами
 * через запятую, и на товарах с несколькими персонажами/аналогами (костюмы,
 * автокресла, коляски) этого не хватает. 512 — с запасом, но не безлимитный
 * TEXT: поле заполняется вручную через админку, разумный потолок дисциплинирует
 * и не даёт случайно вставить туда целый абзац.
 *
 * FULLTEXT-индекс ft_search переживает MODIFY без пересоздания — MySQL
 * перестраивает таблицу, но состав индекса не меняется.
 */
class WidenModelWebKeywords extends Migration
{
    public function up(): void
    {
        DB::statement(
            'ALTER TABLE rent_model_web
             MODIFY keywords VARCHAR(512)
             CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL'
        );
    }

    public function down(): void
    {
        // Не сужаем автоматически: если после расширения в поле успели
        // вписать что-то длиннее 256 символов, откат обрежет данные.
        // Сузить руками, проверив реальную максимальную длину, если понадобится.
    }
}
