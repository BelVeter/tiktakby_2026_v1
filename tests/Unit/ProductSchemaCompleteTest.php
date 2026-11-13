<?php

namespace Tests\Unit;

use App\MyClasses\L3Page;
use PHPUnit\Framework\TestCase;

/**
 * Search Console (ноябрь 2026): Product без name / image — критическая ошибка,
 * страницы не допускаются к расширенной выдаче. Такую разметку не выводим.
 */
class ProductSchemaCompleteTest extends TestCase
{
    public function testCompleteProductIsAllowed()
    {
        $this->assertTrue(L3Page::isProductSchemaComplete('Люлька PRIAM', ['https://tiktak.by/a.webp']));
    }

    public function testEmptyNameIsRejected()
    {
        $this->assertFalse(L3Page::isProductSchemaComplete('', ['https://tiktak.by/a.webp']));
        $this->assertFalse(L3Page::isProductSchemaComplete("  \n ", ['https://tiktak.by/a.webp']));
        $this->assertFalse(L3Page::isProductSchemaComplete(null, ['https://tiktak.by/a.webp']));
    }

    public function testNoImagesIsRejected()
    {
        $this->assertFalse(L3Page::isProductSchemaComplete('Люлька PRIAM', []));
    }
}
