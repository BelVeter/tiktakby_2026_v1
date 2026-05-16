<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function test_root_redirects_to_ru()
    {
        $response = $this->get('/');

        $response->assertRedirect('/ru/');
    }
}
