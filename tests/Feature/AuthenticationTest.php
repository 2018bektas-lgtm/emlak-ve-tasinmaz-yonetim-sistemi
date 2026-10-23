<?php

namespace Tests\Feature;

use App\Models\Kullanici;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_giris_sayfasi_gosterilir(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Kurumsal hesabınızla oturum açın', false);
    }

    public function test_kullanici_mail_ile_giris_yapar(): void
    {
        $kullanici = Kullanici::factory()->create();

        $yanit = $this->post(route('login.store'), [
            'giris' => $kullanici->mail,
            'sifre' => 'password',
        ]);

        $this->assertAuthenticatedAs($kullanici);
        $yanit->assertRedirect(route('panel.dashboard'));
    }

    public function test_kullanici_adi_ile_de_giris_yapilir(): void
    {
        $kullanici = Kullanici::factory()->create();

        $this->post(route('login.store'), [
            'giris' => $kullanici->kullanici_adi,
            'sifre' => 'password',
        ]);

        $this->assertAuthenticatedAs($kullanici);
    }

    public function test_yanlis_sifre_ile_giris_yapilmaz(): void
    {
        $kullanici = Kullanici::factory()->create();

        $this->post(route('login.store'), [
            'giris' => $kullanici->mail,
            'sifre' => 'yanlis-sifre',
        ])->assertSessionHasErrors('giris');

        $this->assertGuest();
    }

    public function test_pasif_hesap_giris_yapamaz(): void
    {
        $kullanici = Kullanici::factory()->create(['aktif_mi' => false]);

        $this->post(route('login.store'), [
            'giris' => $kullanici->mail,
            'sifre' => 'password',
        ])->assertSessionHasErrors('giris');

        $this->assertGuest();
    }

    public function test_giris_yapan_kullanici_paneli_gorur(): void
    {
        $kullanici = Kullanici::factory()->create();

        $this->actingAs($kullanici)
            ->get(route('panel.dashboard'))
            ->assertOk()
            ->assertSee($kullanici->ad);
    }

    public function test_misafir_panelden_yonlendirilir(): void
    {
        // Misafirler kök adrese düşer; orası da giriş sayfasına yönlendirir.
        $this->get(route('panel.dashboard'))->assertRedirect('/');
    }

    public function test_giris_yapan_kullanici_giris_sayfasindan_yonlendirilir(): void
    {
        $kullanici = Kullanici::factory()->create();

        $this->actingAs($kullanici)
            ->get(route('login'))
            ->assertRedirect(route('panel.dashboard'));
    }

    public function test_cikis_oturumu_kapatir(): void
    {
        $kullanici = Kullanici::factory()->create();

        $this->actingAs($kullanici)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
