<?php

namespace Tests\Unit;

use bb\Base;
use PHPUnit\Framework\TestCase;

/**
 * «Доп. информация» клиента в «Звонках» (bb/zv_ch.php) выводится как текст: старые
 * спам-сообщения с <img> растягивали таблицу шире экрана и были дырой для скриптов.
 */
class ClientTextHtmlTest extends TestCase
{
    /** @dataProvider cases */
    public function test_client_text_html(?string $in, string $expected): void
    {
        $this->assertSame($expected, Base::clientTextHtml($in));
    }

    public function cases(): array
    {
        return [
            'spam image and link become text' => [
                '<a href=https://spam.example><img src="https://spam.example/a.jpg"></a>',
                '&lt;a href=https://spam.example&gt;&lt;img src=&quot;https://spam.example/a.jpg&quot;&gt;&lt;/a&gt;',
            ],
            'script' => ['<script>alert(1)</script>', '&lt;script&gt;alert(1)&lt;/script&gt;'],
            'service br kept' => ['<br>e-mail:a@b.by', '<br>e-mail:a@b.by'],
            'xhtml br kept' => ['рост <100<br />ок', 'рост &lt;100<br>ок'],
            'stored entity not double-encoded' => ['EcoToys &quot;Догони меня&quot;', 'EcoToys &quot;Догони меня&quot;'],
            'apostrophe' => ["Мар'яна", 'Мар&#039;яна'],
            'null' => [null, ''],
        ];
    }
}
