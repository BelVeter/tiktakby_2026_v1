<?php

namespace Tests\Unit;

use App\MyClasses\CatMainPage;
use Tests\TestCase;

class CatMainPageCanonicalTest extends TestCase
{
    private function pageAt(string $uri): CatMainPage
    {
        $this->app['request'] = \Illuminate\Http\Request::create($uri, 'GET');
        return new CatMainPage();
    }

    public function test_page_one_canonical_has_no_query_string()
    {
        $p = $this->pageAt('/ru/prokat-detskih-tovarov/toys-for-rent/prokat-igrushek');

        $this->assertSame(
            'http://localhost/ru/prokat-detskih-tovarov/toys-for-rent/prokat-igrushek',
            $p->getCanonicalUrlBy()
        );
    }

    public function test_paginated_page_canonicalizes_to_itself_not_page_one()
    {
        $p = $this->pageAt('/ru/prokat-detskih-tovarov/toys-for-rent/prokat-igrushek?page=2');

        $this->assertSame(
            'http://localhost/ru/prokat-detskih-tovarov/toys-for-rent/prokat-igrushek?page=2',
            $p->getCanonicalUrlBy()
        );
    }

    public function test_paginated_page_canonical_drops_filter_params()
    {
        $p = $this->pageAt('/ru/prokat-detskih-tovarov/toys-for-rent/prokat-igrushek?page=3&gender=boy&rost=90');

        $this->assertSame(
            'http://localhost/ru/prokat-detskih-tovarov/toys-for-rent/prokat-igrushek?page=3',
            $p->getCanonicalUrlBy()
        );
    }
}
