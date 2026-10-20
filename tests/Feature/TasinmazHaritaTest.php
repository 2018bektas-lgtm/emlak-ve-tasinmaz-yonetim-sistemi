<?php

namespace Tests\Feature;

use App\Models\Izin;
use App\Models\Kullanici;
use App\Models\Rol;
use Database\Seeders\YetkiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TasinmazHaritaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(YetkiSeeder::class);
    }

    public function test_harita_arac_cubugunda_ana_sayfa_butonu_var(): void
    {
        $rol = Rol::query()->where('kod', 'goruntuleyici')->firstOrFail();
        $kullanici = Kullanici::factory()->create([
            'rol_id' => $rol->id,
            'aktif_mi' => true,
        ]);

        $this->actingAs($kullanici)
            ->get(route('panel.tasinmazlar.harita'))
            ->assertOk()
            ->assertSee('Ana Sayfa', false)
            ->assertSee('href="'.route('panel.dashboard').'"', false)
            ->assertSee('title="Ana sayfaya dön"', false);
    }

    public function test_harita_yetkisi_olan_giriste_haritaya_gider(): void
    {
        $kullanici = $this->kullanici('goruntuleyici');

        $this->post(route('login.store'), [
            'giris' => $kullanici->mail,
            'sifre' => 'password',
        ])->assertRedirect(route('panel.tasinmazlar.harita'));

        $this->assertAuthenticated();
    }

    public function test_harita_yetkisi_olmayan_giriste_ozete_gider(): void
    {
        $kullanici = $this->haritasizKullanici();

        $this->post(route('login.store'), [
            'giris' => $kullanici->mail,
            'sifre' => 'password',
        ])->assertRedirect(route('panel.dashboard'));
    }

    public function test_giriste_onceden_istenen_adres_korunur(): void
    {
        $kullanici = $this->kullanici('goruntuleyici');

        $this->get(route('panel.tasinmazlar.index'));

        $this->post(route('login.store'), [
            'giris' => $kullanici->mail,
            'sifre' => 'password',
        ])->assertRedirect(route('panel.tasinmazlar.index'));
    }

    public function test_acik_oturum_koku_harita_yetkisine_gore_yonlenir(): void
    {
        $this->actingAs($this->kullanici('goruntuleyici'))
            ->get('/')
            ->assertRedirect(route('panel.tasinmazlar.harita'));

        $this->actingAs($this->haritasizKullanici())
            ->get('/')
            ->assertRedirect(route('panel.dashboard'));
    }

    private function kullanici(string $rolKodu): Kullanici
    {
        $rol = Rol::query()->where('kod', $rolKodu)->firstOrFail();

        return Kullanici::factory()->create([
            'rol_id' => $rol->id,
            'aktif_mi' => true,
        ]);
    }

    private function haritasizKullanici(): Kullanici
    {
        $rol = Rol::query()->create([
            'kod' => 'haritasiz',
            'ad' => 'Haritasız',
            'sistem_mi' => false,
            'sira' => 9,
        ]);
        $rol->izinler()->sync([
            Izin::query()->where('kod', 'tasinmaz.goruntule')->value('id'),
        ]);

        return Kullanici::factory()->create([
            'rol_id' => $rol->id,
            'aktif_mi' => true,
        ]);
    }
}
