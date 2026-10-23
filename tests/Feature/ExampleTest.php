<?php

namespace Tests\Feature;

use App\Models\Kullanici;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_kok_adres_misafiri_giris_sayfasina_yonlendirir(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_kok_adres_kullaniciyi_acilis_sayfasina_yonlendirir(): void
    {
        $kullanici = Kullanici::factory()->create();

        $this->actingAs($kullanici)
            ->get('/')
            ->assertRedirect($kullanici->acilisRotasi());
    }
}
