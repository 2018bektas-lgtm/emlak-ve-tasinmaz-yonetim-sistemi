<?php

namespace Tests\Feature;

use App\Models\Il;
use App\Models\Ilce;
use App\Models\Izin;
use App\Models\Kullanici;
use App\Models\Mahalle;
use App\Models\Rol;
use App\Models\Tasinmaz;
use Database\Seeders\YetkiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    public function test_parsel_bilgi_paneli_harita_yetkisiyle_acilir(): void
    {
        $il = Il::query()->create(['tkgm_id' => 6, 'ad' => 'Ankara']);
        $ilce = Ilce::query()->create(['tkgm_id' => 1234, 'il_id' => $il->id, 'ad' => 'Çankaya']);
        $mahalle = Mahalle::query()->create(['tkgm_id' => 9101, 'ilce_id' => $ilce->id, 'ad' => 'Kızılay']);
        $tasinmaz = Tasinmaz::query()->create([
            'il_id' => $il->id,
            'ilce_id' => $ilce->id,
            'mahalle_id' => $mahalle->id,
            'ada' => '1234',
            'parsel' => '5',
            'alan' => 450,
            'nitelik' => 'Arsa',
        ]);

        $this->actingAs($this->kullanici('goruntuleyici'))
            ->get(route('panel.ajax.tasinmaz-panel', $tasinmaz->id))
            ->assertOk()
            ->assertSee('Kızılay Mah.', false)
            ->assertSee('Kimlik &amp; Konum', false)
            ->assertSee('Hisseler', false);

        $this->actingAs($this->haritasizKullanici())
            ->get(route('panel.ajax.tasinmaz-panel', $tasinmaz->id))
            ->assertForbidden();
    }

    public function test_cevre_analizi_abb_ve_osm_verisiyle_puanlanir(): void
    {
        $this->cevreServisleriniTaklitEt();
        $tasinmaz = $this->koordinatliTasinmaz();

        $this->actingAs($this->kullanici('goruntuleyici'))
            ->get(route('panel.ajax.tasinmaz-cevre', $tasinmaz->id))
            ->assertOk()
            ->assertSee('Konum Endeksi', false)
            ->assertSee('Kurtuluş Parkı', false)       // ABB adı Türkçe başlık biçiminde
            ->assertSee('Atatürk İlkokulu', false)     // OSM'den eğitim
            ->assertSee('© OpenStreetMap', false)
            ->assertDontSee('Veri şu an alınamadı', false);
    }

    public function test_osm_yanit_vermezse_kategoriler_sifir_degil_veri_yok_sayilir(): void
    {
        $this->cevreServisleriniTaklitEt(osmCalisiyor: false);
        $tasinmaz = $this->koordinatliTasinmaz();

        $this->actingAs($this->kullanici('goruntuleyici'))
            ->get(route('panel.ajax.tasinmaz-cevre', $tasinmaz->id))
            ->assertOk()
            ->assertSee('Kurtuluş Parkı', false)
            ->assertSee('Veri şu an alınamadı', false)
            ->assertDontSee('© OpenStreetMap', false);
    }

    public function test_saha_analizi_resmi_imar_risk_ve_kisitlari_gosterir(): void
    {
        $this->cevreServisleriniTaklitEt();
        $tasinmaz = $this->koordinatliTasinmaz();
        $imarDurumu = \App\Models\ImarDurumu::query()->create(['ad' => 'LOJİSTİK TESİS ALANI', 'aktif_mi' => true]);
        $tasinmaz->imar()->create(['imar_durumu_id' => $imarDurumu->id]);

        $this->actingAs($this->kullanici('goruntuleyici'))
            ->get(route('panel.ajax.tasinmaz-saha', $tasinmaz->id))
            ->assertOk()
            ->assertSee('SAĞLIK TESİSİ ALANI', false)        // e-İmar kod sözlüğünden çözüldü
            ->assertSee('Kayıtla uyuşmuyor', false)          // sistem kaydı farklı
            ->assertSee('Kızılay Kentsel Sit', false)
            ->assertSee('Karakeçili yakınları', false)       // USGS yer adı Türkçeleştirildi
            ->assertSee('Altyapı &amp; Arazi', false);
    }

    public function test_imar_turleri_resmi_renkleriyle_ad_altinda_toplanir(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'baskentcbs.ankara.bel.tr/*/uipfiltreicinMekansal/MapServer/19*' => Http::response(['drawingInfo' => ['renderer' => ['uniqueValueInfos' => [
                ['value' => '1020405017', 'label' => 'REKREATİF ALAN', 'symbol' => ['color' => [255, 211, 127, 255]]],
                ['value' => '1020405004', 'label' => 'REKREATİF ALAN', 'symbol' => ['color' => [255, 211, 127, 255]]],
                ['value' => '1020203002', 'label' => '1020203002', 'symbol' => ['color' => [102, 153, 205, 255]]], // etiketsiz
                ['value' => '1040309001', 'label' => 'SU YÜZEYİ', 'symbol' => ['color' => [115, 223, 235, 255]]],
            ]]]]),
        ]);

        $this->actingAs($this->kullanici('goruntuleyici'))
            ->getJson(route('panel.ajax.imar-turleri'))
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['ad' => 'REKREATİF ALAN', 'kodlar' => [1020405017, 1020405004], 'renk' => '#ffd37f'])
            ->assertJsonFragment(['ad' => 'SU YÜZEYİ', 'kodlar' => [1040309001], 'renk' => '#73dfeb']);
    }

    public function test_tasinmaz_raporu_gorsellerle_uretilir(): void
    {
        $this->cevreServisleriniTaklitEt();
        $il = Il::query()->create(['tkgm_id' => 6, 'ad' => 'Ankara']);
        $ilce = Ilce::query()->create(['tkgm_id' => 1234, 'il_id' => $il->id, 'ad' => 'Çankaya']);
        $mahalle = Mahalle::query()->create(['tkgm_id' => 9102, 'ilce_id' => $ilce->id, 'ad' => 'Kızılay']);
        $tasinmaz = Tasinmaz::query()->create([
            'il_id' => $il->id,
            'ilce_id' => $ilce->id,
            'mahalle_id' => $mahalle->id,
            'ada' => '1234',
            'parsel' => '6',
            'alan' => 450,
            'nitelik' => 'Arsa',
        ]);
        $tasinmaz->koordinat()->create([
            'koordinat' => ['type' => 'Polygon', 'coordinates' => [[[32.854, 39.920], [32.855, 39.920], [32.855, 39.921], [32.854, 39.921], [32.854, 39.920]]]],
        ]);

        $this->actingAs($this->kullanici('goruntuleyici'))
            ->get($tasinmaz->fresh()->rota('rapor'))
            ->assertOk()
            ->assertSee('Taşınmaz Raporu', false)
            ->assertSee('uip_esri3/MapServer/tile/', false)
            ->assertSee('lyrs=s', false)
            ->assertSee('class="rh-sinir"', false)
            ->assertSee('Çevresel Analiz', false)
            ->assertSee('class="halka-1000"', false);
    }

    /** Kızılay'da koordinatlı bir taşınmaz. */
    private function koordinatliTasinmaz(): Tasinmaz
    {
        $il = Il::query()->create(['tkgm_id' => 6, 'ad' => 'Ankara']);
        $ilce = Ilce::query()->create(['tkgm_id' => 1234, 'il_id' => $il->id, 'ad' => 'Çankaya']);
        $mahalle = Mahalle::query()->create(['tkgm_id' => 9103, 'ilce_id' => $ilce->id, 'ad' => 'Kızılay']);
        $tasinmaz = Tasinmaz::query()->create([
            'il_id' => $il->id,
            'ilce_id' => $ilce->id,
            'mahalle_id' => $mahalle->id,
            'ada' => '1234',
            'parsel' => '7',
            'alan' => 450,
            'nitelik' => 'Arsa',
        ]);
        $tasinmaz->koordinat()->create([
            'koordinat' => ['type' => 'Polygon', 'coordinates' => [[[32.854, 39.920], [32.855, 39.920], [32.855, 39.921], [32.854, 39.921], [32.854, 39.920]]]],
        ]);

        return $tasinmaz;
    }

    /** Çevre analizinin dış servisleri: ABB kent rehberi, Overpass ve Open-Meteo. Testler internete çıkmaz. */
    private function cevreServisleriniTaklitEt(bool $osmCalisiyor = true): void
    {
        Http::preventStrayRequests();
        Http::fake([
            // Saha analizi: sit alanı içinde, resmî imar "Sağlık Tesisi Alanı"
            'baskentcbs.ankara.bel.tr/*/kudem/kudemGoruntuleme/MapServer/2/*' => Http::response(['features' => [
                ['attributes' => ['adi' => 'Kızılay Kentsel Sit', 'derecesi' => 'I'], 'geometry' => ['rings' => [[[32.85, 39.91], [32.86, 39.91], [32.86, 39.93], [32.85, 39.93], [32.85, 39.91]]]]],
            ]]),
            'baskentcbs.ankara.bel.tr/*/uipfiltreicinMekansal/MapServer/19/query*' => Http::response(['features' => [
                ['attributes' => ['altkullanim' => '1101', 'kaks' => 1.2, 'katadedi' => '2', 'hmax' => '2 Kat'], 'geometry' => null],
            ]]),
            'baskentcbs.ankara.bel.tr/*/uipfiltreicinMekansal/MapServer/19*' => Http::response(['drawingInfo' => ['renderer' => ['uniqueValueInfos' => [
                ['value' => '1101', 'label' => 'SAĞLIK TESİSİ ALANI'],
            ]]], 'fields' => []]),
            'earthquake.usgs.gov/*' => Http::response(['features' => [
                ['properties' => ['mag' => 5.7, 'time' => 1198108800000, 'place' => '24 km SW of Karakeçili, Turkey'], 'geometry' => ['coordinates' => [33.2, 39.5, 10]]],
            ]]),
            'baskentcbs.ankara.bel.tr/*/abb_kent_rehberi/MapServer/5/*' => Http::response(['features' => [
                ['attributes' => ['adi' => 'ABB  KURTULUŞ PARKI'], 'geometry' => ['x' => 32.8560, 'y' => 39.9215]],
            ]]),
            'baskentcbs.ankara.bel.tr/*/ego_kent_Rehberi/MapServer/8/*' => Http::response(['features' => [
                ['attributes' => ['durak_no' => '11595', 'durak_adi' => 'KIZILAY'], 'geometry' => ['x' => 32.8548, 'y' => 39.9203]],
            ]]),
            // Diğer ABB katmanları boş; park katmanının gerçek davranışı gibi hata da dönebilir.
            'baskentcbs.ankara.bel.tr/*' => Http::response(['features' => []]),
            'overpass-api.de/*' => $osmCalisiyor
                ? Http::response(['elements' => [
                    ['type' => 'node', 'lat' => 39.9220, 'lon' => 32.8550, 'tags' => ['amenity' => 'school', 'name' => 'Atatürk İlkokulu']],
                    ['type' => 'node', 'lat' => 39.9200, 'lon' => 32.8530, 'tags' => ['amenity' => 'pharmacy']],
                ]])
                : Http::response('Gateway Time-out', 504),
            'overpass.kumi.systems/*' => Http::response('Gateway Time-out', 504),
            'api.open-meteo.com/*' => Http::response(['elevation' => [890.0]]),
        ]);
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
