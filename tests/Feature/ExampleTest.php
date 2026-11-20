<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * トップを開くと、Inertia の仮のページ（Home）が返る
     */
    public function test_top_page_renders_home_with_inertia(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('appName', config('app.name'))
        );
    }
}
