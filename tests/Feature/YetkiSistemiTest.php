<?php

namespace Tests\Feature;

use App\Models\Il;
use App\Models\Ilce;
use App\Models\Kullanici;
use App\Models\Mahalle;
use App\Models\Rol;
use App\Models\Tasinmaz;
use App\Support\TasinmazKolonlari;
use Database\Seeders\YetkiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class YetkiSistemiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(YetkiSeeder::class);
    }

    public function test_admin_tum_izinlere_sahiptir(): void
    {
        $admin = $this->kullanici('admin');

        $this->assertTrue($admin->adminMi());
        $this->assertTrue($admin->izinVarMi('tasinmaz.sil'));
        $this->assertTrue($admin->izinVarMi('rol.duzenle'));
    }

    public function test_goruntuleyici_duzenleyemez(): void
    {
        $kullanici = $this->kullanici('goruntuleyici');

        $this->assertTrue($kullanici->izinVarMi('tasinmaz.goruntule'));
        $this->assertFalse($kullanici->izinVarMi('tasinmaz.olustur'));
        $this->assertFalse($kullanici->izinVarMi('tasinmaz.duzenle'));
        $this->assertFalse($kullanici->izinVarMi('tasinmaz.sil'));
    }

    public function test_izinsiz_rota_403_doner(): void
    {
        $kullanici = $this->kullanici('goruntuleyici');

        $this->actingAs($kullanici)
            ->get(route('panel.tasinmazlar.olustur'))
            ->assertForbidden();

        $this->actingAs($kullanici)
            ->get(route('panel.kullanicilar.index'))
            ->assertForbidden();
    }

    public function test_izinli_kullanici_listeyi_gorur(): void
    {
        $kullanici = $this->kullanici('kullanici');

        $this->actingAs($kullanici)
            ->get(route('panel.tasinmazlar.index'))
            ->assertOk();
    }

    public function test_izinli_kullanici_tum_tasinmazlari_gorur(): void
    {
        // Müdürlük ayrımı yok: görüntüleme izni olan herkes tüm kayıtları görür
        $a = $this->tasinmazOlustur('10', '10');
        $b = $this->tasinmazOlustur('20', '20');

        $this->actingAs($this->kullanici('goruntuleyici'))
            ->get(route('panel.tasinmazlar.index'))
            ->assertOk()
            ->assertSee('data-tasinmaz-id="'.$a->id.'"', false)
            ->assertSee('data-tasinmaz-id="'.$b->id.'"', false);
    }

    public function test_pasif_kullanici_giris_yapamaz(): void
    {
        $kullanici = $this->kullanici('kullanici');
        $kullanici->update(['aktif_mi' => false]);

        $this->post(route('login.store'), [
            'giris' => $kullanici->kullanici_adi,
            'sifre' => 'password',
        ])->assertSessionHasErrors('giris');

        $this->assertGuest();
    }

    public function test_admin_rol_listesini_gorur(): void
    {
        $this->actingAs($this->kullanici('admin'))
            ->get(route('panel.roller.index'))
            ->assertOk()
            ->assertSee('Yönetici (Admin)', false);
    }

    public function test_kolon_tercihi_kaydedilir(): void
    {
        $kullanici = $this->kullanici('kullanici');

        $this->actingAs($kullanici)
            ->postJson(route('panel.kolon-tercihi.kaydet'), [
                'tablo' => 'tasinmazlar',
                'kolonlar' => ['ada', 'parsel', 'nitelik'],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame(
            ['ada', 'parsel', 'nitelik'],
            TasinmazKolonlari::kullaniciIcin($kullanici->fresh())
        );
    }

    private function kullanici(string $rolKodu): Kullanici
    {
        $rol = Rol::query()->where('kod', $rolKodu)->firstOrFail();

        return Kullanici::factory()->create([
            'rol_id' => $rol->id,
            'aktif_mi' => true,
        ]);
    }

    private function tasinmazOlustur(string $ada, string $parsel): Tasinmaz
    {
        static $sira = 1;

        $il = Il::query()->first() ?? Il::query()->create(['tkgm_id' => 6, 'ad' => 'Ankara']);
        $ilce = Ilce::query()->first() ?? Ilce::query()->create([
            'tkgm_id' => 1234,
            'il_id' => $il->id,
            'ad' => 'Çankaya',
        ]);
        $mahalle = Mahalle::query()->where('tkgm_id', 9000 + $sira)->first() ?? Mahalle::query()->create([
            'tkgm_id' => 9000 + $sira,
            'ilce_id' => $ilce->id,
            'ad' => 'Test Mahalle '.$sira,
        ]);
        $sira++;

        return Tasinmaz::query()->create([
            'mahalle_id' => $mahalle->id,
            'ada' => $ada,
            'parsel' => $parsel,
            'alan' => 100,
            'nitelik' => 'Arsa',
            'uzeri_bina_var_mi' => false,
        ]);
    }
}
