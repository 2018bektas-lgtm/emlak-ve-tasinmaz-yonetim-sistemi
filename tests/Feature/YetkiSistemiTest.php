<?php

namespace Tests\Feature;

use App\Models\Il;
use App\Models\Ilce;
use App\Models\Kullanici;
use App\Models\Mahalle;
use App\Models\Mudurluk;
use App\Models\Rol;
use App\Models\Tasinmaz;
use App\Support\TasinmazKolonlari;
use Database\Seeders\MudurlukSeeder;
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
        $this->seed(MudurlukSeeder::class);
    }

    public function test_admin_tum_izinlere_sahiptir(): void
    {
        $admin = $this->kullanici('admin');

        $this->assertTrue($admin->adminMi());
        $this->assertTrue($admin->izinVarMi('tasinmaz.sil'));
        $this->assertTrue($admin->izinVarMi('rol.duzenle'));
        $this->assertTrue($admin->izinVarMi('tasinmaz.tumunu-gor'));
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

    public function test_mudurluk_kapsami_diger_kayitlari_gizler(): void
    {
        $emlak = Mudurluk::query()->where('kod', 'emlak-istimlak')->firstOrFail();
        $imar = Mudurluk::query()->where('kod', 'imar-sehircilik')->firstOrFail();

        $kullanici = $this->kullanici('kullanici', $emlak);
        $this->tasinmazOlustur($emlak, '10', '10');
        $gizli = $this->tasinmazOlustur($imar, '20', '20');

        $this->actingAs($kullanici)
            ->get(route('panel.tasinmazlar.index'))
            ->assertOk()
            ->assertSee('10', false)
            ->assertDontSee('>'.$gizli->id.'<', false);

        $gorunen = Tasinmaz::query()->mudurlukKapsami($kullanici)->pluck('id');
        $this->assertTrue($gorunen->contains(fn ($id) => Tasinmaz::query()->where('mudurluk_id', $emlak->id)->whereKey($id)->exists()));
        $this->assertFalse($gorunen->contains($gizli->id));
    }

    public function test_admin_tum_mudurlukleri_gorur(): void
    {
        $emlak = Mudurluk::query()->where('kod', 'emlak-istimlak')->firstOrFail();
        $imar = Mudurluk::query()->where('kod', 'imar-sehircilik')->firstOrFail();
        $this->tasinmazOlustur($emlak, '10', '10');
        $this->tasinmazOlustur($imar, '20', '20');

        $admin = $this->kullanici('admin');
        $this->assertSame(2, Tasinmaz::query()->mudurlukKapsami($admin)->count());
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

    private function kullanici(string $rolKodu, ?Mudurluk $mudurluk = null): Kullanici
    {
        $rol = Rol::query()->where('kod', $rolKodu)->firstOrFail();

        return Kullanici::factory()->create([
            'rol_id' => $rol->id,
            'mudurluk_id' => $mudurluk?->id,
            'aktif_mi' => true,
        ]);
    }

    private function tasinmazOlustur(Mudurluk $mudurluk, string $ada, string $parsel): Tasinmaz
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
            'mudurluk_id' => $mudurluk->id,
            'uzeri_bina_var_mi' => false,
        ]);
    }
}
