<?php

namespace Tests\Feature\Bb;

use Tests\TestCase;

/**
 * bb/kb.php («Карнавал. Брони.») — входная точка карнавального раздела: на неё
 * ведёт пункт «Карнавал» общей панели bb/bb_nav.php. Остальные карнавальные
 * страницы доступны только по ссылкам из kb.php.
 *
 * 18.02.2026 (PR #49) собственное меню kb.php заменили на общую панель, и вместе
 * с ним пропали ссылки на таблицу броней, «свободные по дате», удалённые брони и
 * карнавальные заявки. Вне сезона этого не заметили; в ноябре сотрудники открыть
 * таблицу уже не могли (только по одному инв. № через кнопку у брони), а заявки
 * с сайта (kb_zayavki) никто не видел.
 */
class KbCarnivalMenuTest extends TestCase
{
    /** @return array<string, array{string}> */
    public function carnivalPages(): array
    {
        return [
            'Карнавальная таблица' => ['/bb/kb_lines.php'],
            'Свободные по дате'    => ['/bb/karn_free.php'],
            'Удаленные брони'      => ['/bb/kb_arch.php'],
            'Карнавальные заявки'  => ['/bb/kb_zayavki.php'],
        ];
    }

    /** @dataProvider carnivalPages */
    public function test_kb_page_links_to_carnival_page(string $href): void
    {
        $root = dirname(__DIR__, 3);

        $this->assertFileExists($root . $href, "страница $href удалена — убрать её и из меню kb.php");

        $code = file_get_contents($root . '/bb/kb.php');
        $this->assertTrue(strpos($code, 'href="' . $href . '"') !== false,
            "в меню bb/kb.php нет ссылки на $href — сотрудники не смогут открыть страницу");
    }
}
