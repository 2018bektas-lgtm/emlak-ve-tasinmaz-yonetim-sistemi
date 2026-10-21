<?php

namespace Tests\Feature;

use App\Models\Il;
use App\Models\Ilce;
use App\Models\KiraAlan;
use App\Models\KiraDosyasi;
use App\Models\KiraPoligon;
use App\Models\KiraSozlesme;
use App\Models\Kullanici;
use App\Models\Mahalle;
use App\Models\Rol;
use App\Models\Tasinmaz;
use Database\Seeders\YetkiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Kira kaydı akışı: önce kira alanı (taşınmaz) eklenir, kiraya verildiğinde
 * dosya numarası bağlanır. Numara yeniyse dosya açılır, aynı yerde varsa yeni
 * dönem olur, başka yerde varsa alan o dosyaya ek yer olarak bağlanır.
 */
class KiraAlanKayitTest extends TestCase
{
    use RefreshDatabase;

    private Mahalle $mahalle;

    /** Göksu parkı içinde küçük bir kare */
    private const SINIR = ['type' => 'Polygon', 'coordinates' => [[[32.9540, 39.8537], [32.9546, 39.8537], [32.9546, 39.8541], [32.9540, 39.8541], [32.9540, 39.8537]]]];

    /** Başka bir yer */
    private const BASKA_SINIR = ['type' => 'Polygon', 'coordinates' => [[[32.8000, 39.9000], [32.8004, 39.9000], [32.8004, 39.9003], [32.8000, 39.9003], [32.8000, 39.9000]]]];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(YetkiSeeder::class);
        $this->actingAs(Kullanici::factory()->create([
            'rol_id' => Rol::query()->where('kod', 'admin')->firstOrFail()->id,
            'aktif_mi' => true,
        ]));

        $il = Il::create(['ad' => 'Ankara', 'tkgm_id' => 28]);
        $ilce = Ilce::create(['ad' => 'Mamak', 'il_id' => $il->id, 'tkgm_id' => 177]);
        $this->mahalle = Mahalle::create(['ad' => 'Kusunlar-İmar', 'ilce_id' => $ilce->id, 'tkgm_id' => 123244]);
    }

    public function test_bos_alan_dosyasiz_eklenir_ve_sinirdaki_envanter_baglanir(): void
    {
        $tasinmaz = $this->envanter(32.9543, 39.8539);
        $this->envanter(32.9600, 39.8600); // sınırın dışında

        $this->post(route('panel.kira.alanlar.kaydet'), $this->alan(['dolu_bos' => 'bos']))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $alan = KiraAlan::firstOrFail();
        $this->assertNull($alan->kira_dosya_id);
        $this->assertSame('bos', $alan->dolu_bos->value);
        $this->assertNotNull($alan->poligon_id);
        $this->assertSame($tasinmaz->id, $alan->tasinmaz_id);
        $this->assertSame(0, KiraDosyasi::count());

        // Dosyası olmayan alanın kartı ve haritası açılır
        $this->get(route('panel.kira.alanlar.detay', $alan->id))->assertOk()->assertSee('Kiraya Ver');
        $this->getJson(route('panel.kira.harita.geojson'))->assertOk()->assertJsonPath('features.0.properties.alan_id', $alan->id);
        $this->get(route('panel.kira.alanlar.index'))->assertOk()->assertSee('Dosya yok');
    }

    public function test_dolu_alan_yeni_dosya_numarasiyla_dosya_acar(): void
    {
        $this->post(route('panel.kira.alanlar.kaydet'), $this->alan() + $this->kiralama('K-0050', 'İlk Kiracı'))
            ->assertSessionHasNoErrors();

        $dosya = KiraDosyasi::where('dosya_no', 'K-0050')->firstOrFail();
        $alan = KiraAlan::firstOrFail();
        $this->assertSame($dosya->id, $alan->kira_dosya_id);
        $this->assertSame('Göksu Büfe', $dosya->isyeri_adi, 'işyeri adı boşsa alan adı');
        $this->assertSame((string) $this->mahalle->id, (string) $dosya->tasinmaz->mahalle_id, 'alanın konumu dosyanın konumu olur');

        // Kira bilgisi bir kez girildi; alan dosyanın dönemiyle eşit
        $this->assertSame('İlk Kiracı', $alan->kiraci_ad_unvan);
        $this->assertSame(120000.0, (float) $alan->yillik_bedel);
        $this->assertSame(1, KiraSozlesme::count());
        $this->assertDatabaseHas('kira_kiracilari', ['ad_unvan' => 'İlk Kiracı', 'kira_sozlesme_id' => KiraSozlesme::value('id')]);
    }

    public function test_ayni_yerde_ayni_dosya_numarasi_yeni_donem_olur(): void
    {
        $this->post(route('panel.kira.alanlar.kaydet'), $this->alan() + $this->kiralama('K-0050', 'İlk Kiracı'));
        $poligon = KiraPoligon::firstOrFail();

        // Süresi dolmamış kira var: onaysız reddedilir
        $ikinci = $this->alan(['poligon_id' => $poligon->id, 'koordinat' => '']) + $this->kiralama('K-0050', 'Yeni Kiracı', now()->addMonth());
        $this->post(route('panel.kira.alanlar.kaydet'), $ikinci)->assertSessionHasErrors('devam_onayi');
        $this->assertSame(1, KiraSozlesme::count());

        $this->post(route('panel.kira.alanlar.kaydet'), $ikinci + ['devam_onayi' => '1'])->assertSessionHasNoErrors();

        // Yeni alan açılmaz; aynı yerdeki alan yeni kiracıyı gösterir
        $this->assertSame(1, KiraAlan::count());
        $this->assertSame(1, KiraDosyasi::count());
        $this->assertSame(2, KiraSozlesme::count());
        $this->assertSame(1, KiraSozlesme::where('aktif_mi', true)->count());
        $this->assertSame('Yeni Kiracı', KiraAlan::first()->kiraci_ad_unvan);
        $this->assertSame(
            ['İlk Kiracı', 'Yeni Kiracı'],
            DB::table('kira_kiracilari')->orderBy('id')->pluck('ad_unvan')->all(),
            'eski kiracı geçmişte kalır'
        );
    }

    public function test_baska_yerde_ayni_dosya_numarasi_ek_yer_olarak_baglanir(): void
    {
        $this->post(route('panel.kira.alanlar.kaydet'), $this->alan() + $this->kiralama('K-0050', 'İlk Kiracı'));

        // Başka sınırda aynı numara: kira bilgisi istenmez, dosya olduğu gibi bağlanır
        $this->post(route('panel.kira.alanlar.kaydet'), $this->alan([
            'ad' => 'İkinci büfe',
            'koordinat' => json_encode(self::BASKA_SINIR),
            'mahalle_id' => null, 'ada' => null, 'parsel' => null,
        ]) + ['dosya_no' => 'K-0050'])->assertSessionHasNoErrors();

        $dosya = KiraDosyasi::firstOrFail();
        $this->assertSame(1, KiraDosyasi::count());
        $this->assertSame(1, KiraSozlesme::count());
        $this->assertSame(2, KiraAlan::where('kira_dosya_id', $dosya->id)->count());
        $this->assertSame(2, KiraPoligon::count());
        $this->assertSame('İlk Kiracı', KiraAlan::where('ad', 'İkinci büfe')->value('kiraci_ad_unvan'));
    }

    public function test_bos_alan_sonradan_kiraya_verilir(): void
    {
        $this->post(route('panel.kira.alanlar.kaydet'), $this->alan(['dolu_bos' => 'bos']));
        $alan = KiraAlan::firstOrFail();

        $this->get(route('panel.kira.alanlar.duzenle', ['id' => $alan->id, 'kirala' => 1]))
            ->assertOk()
            ->assertSee('name="dosya_no"', false);

        $this->put(route('panel.kira.alanlar.guncelle', $alan->id), $this->alan(['aktif_mi' => '1']) + $this->kiralama('K-0077', 'Sonraki Kiracı'))
            ->assertSessionHasNoErrors();

        $alan->refresh();
        $this->assertSame('dolu', $alan->dolu_bos->value);
        $this->assertSame('K-0077', $alan->dosya->dosya_no);
        $this->assertSame('Sonraki Kiracı', $alan->kiraci_ad_unvan);
        $this->assertSame(1, KiraPoligon::count(), 'aynı parsel için ikinci sınır açılmaz');
    }

    public function test_dosya_numarasi_kontrolu_durumu_soyler(): void
    {
        $this->post(route('panel.kira.alanlar.kaydet'), $this->alan() + $this->kiralama('K-0050', 'İlk Kiracı'));
        $poligon = KiraPoligon::firstOrFail();

        $sor = fn (array $q) => $this->getJson(route('panel.kira.alanlar.dosya-kontrol', $q))->assertOk();

        $sor(['dosya_no' => 'K-9999', 'poligon_id' => $poligon->id])->assertJsonPath('durum', 'yeni');
        $sor(['dosya_no' => 'K-0050', 'poligon_id' => $poligon->id])
            ->assertJsonPath('durum', 'ayni_yer')
            ->assertJsonPath('dosya.kiraci', 'İlk Kiracı');
        $sor(['dosya_no' => 'K-0050'])->assertJsonPath('durum', 'baska_yer');
    }

    public function test_dosya_duzenlenince_alanlar_esitlenir_ve_eski_adres_yonlenir(): void
    {
        $this->post(route('panel.kira.alanlar.kaydet'), $this->alan() + $this->kiralama('K-0050', 'İlk Kiracı'));
        $dosya = KiraDosyasi::firstOrFail();

        $this->get(route('panel.kira.duzenle', $dosya->id))->assertOk();
        $this->put(route('panel.kira.guncelle', $dosya->id), [
            'dosya_no' => 'K-0050', 'dolu_bos' => 'dolu', 'ihale_turu' => 'ihaleli',
            'kiraci_ad_unvan' => 'İlk Kiracı Ltd. Şti.', 'sozlesme_aktif_mi' => '1',
            'baslangic_tarihi' => now()->subMonth()->toDateString(), 'bitis_tarihi' => now()->addYears(2)->toDateString(),
            'bedeller' => [['yillik_bedel' => '150000']],
        ])->assertSessionHasNoErrors();

        $alan = KiraAlan::firstOrFail();
        $this->assertSame('İlk Kiracı Ltd. Şti.', $alan->kiraci_ad_unvan);
        $this->assertSame(150000.0, (float) $alan->yillik_bedel);
        $this->assertNotNull($alan->poligon_id, 'dosya düzenlemesi alanın sınırına dokunmaz');

        $this->get(route('panel.kira.olustur', ['ada' => '50285']))
            ->assertRedirect(route('panel.kira.alanlar.olustur', ['ada' => '50285']));
    }

    /** @return array<string, mixed> */
    private function alan(array $ek = []): array
    {
        return array_replace([
            'ad' => 'Göksu Büfe',
            'alan' => '45',
            'dolu_bos' => 'dolu',
            'il_id' => $this->mahalle->ilce->il_id,
            'ilce_id' => $this->mahalle->ilce_id,
            'mahalle_id' => $this->mahalle->id,
            'ada' => '50285',
            'parsel' => '5',
            'koordinat' => json_encode(self::SINIR),
        ], $ek);
    }

    /** @return array<string, mixed> */
    private function kiralama(string $dosyaNo, string $kiraci, $baslangic = null): array
    {
        $baslangic ??= now()->subMonth();

        return [
            'dosya_no' => $dosyaNo,
            'ihale_turu' => 'ihaleli',
            'sozlesme_aktif_mi' => '1',
            'kiraci_ad_unvan' => $kiraci,
            'baslangic_tarihi' => $baslangic->toDateString(),
            'bitis_tarihi' => $baslangic->copy()->addYears(3)->toDateString(),
            'bedeller' => [['yillik_bedel' => '120000'], ['yillik_bedel' => '150000']],
        ];
    }

    private function envanter(float $lng, float $lat): Tasinmaz
    {
        $t = Tasinmaz::create([
            'il_id' => $this->mahalle->ilce->il_id,
            'ilce_id' => $this->mahalle->ilce_id,
            'mahalle_id' => $this->mahalle->id,
            'ada' => '50285',
            'parsel' => (string) random_int(1, 999),
            'alan' => 3450,
            'nitelik' => 'Arsa',
            'uzeri_bina_var_mi' => false,
        ]);
        $t->koordinat()->create(['lat' => $lat, 'lng' => $lng]);

        return $t;
    }
}
