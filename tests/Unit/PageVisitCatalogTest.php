<?php

namespace Tests\Unit;

use bb\classes\PageVisitCatalog;
use PHPUnit\Framework\TestCase;

class PageVisitCatalogTest extends TestCase
{
    public function test_ajax_prefix_is_technical(): void
    {
        $this->assertTrue(PageVisitCatalog::isTechnical('ajax_client_check.php'));
        $this->assertTrue(PageVisitCatalog::isTechnical('ajax_model_suggest.php'));
    }

    public function test_api_suffix_is_technical(): void
    {
        $this->assertTrue(PageVisitCatalog::isTechnical('redirects_api.php'));
        $this->assertTrue(PageVisitCatalog::isTechnical('a1_calls_api.php'));
    }

    public function test_badge_suffix_is_technical(): void
    {
        $this->assertTrue(PageVisitCatalog::isTechnical('bb_nav_badge.php'));
    }

    public function test_known_library_files_are_technical(): void
    {
        $this->assertTrue(PageVisitCatalog::isTechnical('Db.php'));
        $this->assertTrue(PageVisitCatalog::isTechnical('tovar.php'));
        $this->assertTrue(PageVisitCatalog::isTechnical('client.php'));
        $this->assertTrue(PageVisitCatalog::isTechnical('Base.php'));
    }

    public function test_real_page_is_not_technical(): void
    {
        $this->assertFalse(PageVisitCatalog::isTechnical('deals.php'));
        $this->assertFalse(PageVisitCatalog::isTechnical('tovar_new.php'));
        $this->assertFalse(PageVisitCatalog::isTechnical('kr_baza_new.php'));
    }

    /**
     * dogovor.php, dogovor_new.php, kch.php, akt_print.php etc. all declare an
     * inline helper class/function *inside* a real page (legacy pattern), so a
     * naive "declares a class" check alone would wrongly exclude them — only
     * genuinely bare namespace+class library files belong in the exclusion.
     */
    public function test_real_page_with_inline_class_is_not_technical(): void
    {
        $this->assertFalse(PageVisitCatalog::isTechnical('dogovor.php'));
        $this->assertFalse(PageVisitCatalog::isTechnical('dogovor_new.php'));
        $this->assertFalse(PageVisitCatalog::isTechnical('kch.php'));
        $this->assertFalse(PageVisitCatalog::isTechnical('akt_print.php'));
    }

    public function test_list_trackable_pages_excludes_technical_and_library_files(): void
    {
        $pages = PageVisitCatalog::listTrackablePages();
        $this->assertContains('deals.php', $pages);
        $this->assertNotContains('ajax_client_check.php', $pages);
        $this->assertNotContains('bb_nav_badge.php', $pages);
        $this->assertNotContains('redirects_api.php', $pages);
        $this->assertNotContains('Db.php', $pages);
        $this->assertNotContains('tovar.php', $pages);
    }

    public function test_list_trackable_pages_is_sorted_and_unique(): void
    {
        $pages = PageVisitCatalog::listTrackablePages();
        $sorted = $pages;
        sort($sorted);
        $this->assertSame($sorted, $pages);
        $this->assertSame(count($pages), count(array_unique($pages)));
    }
}
