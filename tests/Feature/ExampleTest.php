<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_homepage_displays_the_login_screen(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Giriş Yap', false);
        $response->assertSee('Arsa', false);
        $response->assertSee('Hesabınıza giriş yapın', false);
    }
}
