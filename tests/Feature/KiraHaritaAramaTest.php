<?php

namespace Tests\Feature;

use App\Models\KiraAlan;
use App\Models\KiraDosyasi;
use App\Models\KiraIsKolu;
use App\Models\KiraKiraci;
use App\Models\KiraPoligon;
use App\Models\Kullanici;
use App\Models\Rol;
use Database\Seeders\YetkiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class KiraHaritaAramaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(YetkiSeeder::class);
    }

    public function test_harita_sorgusu_liste_suzgeclerini_ve_indirmeyi_sunar(): void
    {
        $this->actingAs($this->kullanici())
            ->get(route('panel.kira.harita'))
            ->assertOk()
            ->assertSee('Kira Sorgusu', false)
            ->assertSee('Detaylı sorgu', false)
            ->assertSee('name="is_kolu_id"', false)
            ->assertSee('name="hukuki_durum_id"', false)
            ->assertSee('name="kiracilik_durum_id"', false)
            ->assertSee('Seçilenleri haritada göster', false)
            ->assertSee('>Excel<', false)
            ->assertSee('>KML<', false)
            ->assertSee('>GeoJSON<', false);

        $this->actingAs($this->kullanici())
            ->get(route('panel.kira.index'))
            ->assertOk();
    }

    public function test_is_kolu_ve_secim_sorguyu_listeyi_ve_dosyalari_daraltir(): void
    {
        $bufe = KiraIsKolu::create(['ad' => 'Büfe', 'aktif_mi' => true]);
        $otopark = KiraIsKolu::create(['ad' => 'Otopark', 'aktif_mi' => true]);
        $bufeDosya = $this->dosya('K-1001', $bufe->id, 'Çay ocağı', 32.85, 39.92);
        $digerBufe = $this->dosya('K-1002', $bufe->id, 'İkinci büfe', 32.90, 39.95);
        $this->dosya('K-2002', $otopark->id, 'Kat otopark', 32.80, 39.90);

        $kullanici = $this->kullanici();

        $this->actingAs($kullanici)
            ->getJson(route('panel.kira.harita.ara', ['is_kolu_id' => $bufe->id]))
            ->assertOk()
            ->assertJsonPath('toplam', 2)
            ->assertJsonFragment(['dosya_no' => 'K-1001'])
            ->assertJsonMissing(['dosya_no' => 'K-2002']);

        $this->actingAs($kullanici)
            ->getJson(route('panel.kira.harita.ara', [
                'is_kolu_id' => $bufe->id,
                'ids' => $bufeDosya->id,
            ]))
            ->assertOk()
            ->assertJsonPath('toplam', 1)
            ->assertJsonPath('kayitlar.0.dosya_no', 'K-1001')
            ->assertJsonPath('kayitlar.0.haritada', true);

        $kml = $this->actingAs($kullanici)
            ->get(route('panel.kira.harita.kml', ['is_kolu_id' => $otopark->id]));
        $kml->assertOk();
        $kml->assertHeader('content-type', 'application/vnd.google-earth.kml+xml; charset=UTF-8');
        $this->assertStringContainsString('K-2002', $kml->getContent());
        $this->assertStringNotContainsString('K-1001', $kml->getContent());
        $this->assertStringContainsString('32.8', $kml->getContent());

        $geo = $this->actingAs($kullanici)
            ->get(route('panel.kira.harita.geojson-indir', ['ids' => $bufeDosya->id.','.$digerBufe->id]));
        $geo->assertOk();
        $geo->assertHeader('content-type', 'application/geo+json; charset=UTF-8');
        $govde = $geo->json();
        $this->assertSame('FeatureCollection', $govde['type']);
        $nolar = collect($govde['features'])->pluck('properties.dosya_no')->sort()->values()->all();
        $this->assertSame(['K-1001', 'K-1002'], $nolar);

        $this->actingAs($kullanici)
            ->getJson(route('panel.kira.harita.geojson', ['ids' => $bufeDosya->id]))
            ->assertOk()
            ->assertJsonPath('toplam', 1)
            ->assertJsonPath('features.0.properties.dosya_no', 'K-1001');

        $excel = $this->actingAs($kullanici)
            ->get(route('panel.kira.excel', ['is_kolu_id' => $bufe->id, 'ids' => $digerBufe->id]));
        $excel->assertOk();
        $gecici = tempnam(sys_get_temp_dir(), 'kira-xlsx');
        file_put_contents($gecici, $excel->streamedContent());
        $hucre = IOFactory::load($gecici)->getActiveSheet()->getCell('A2')->getValue();
        @unlink($gecici);
        $this->assertSame('K-1002', $hucre);
    }

    private function dosya(string $no, int $isKoluId, string $isyeri, float $lng, float $lat): KiraDosyasi
    {
        $dosya = KiraDosyasi::create([
            'dosya_no' => $no,
            'isyeri_adi' => $isyeri,
            'is_kolu_id' => $isKoluId,
            'dolu_bos' => 'dolu',
            'ihale_turu' => 'ihalesiz',
        ]);
        KiraKiraci::create([
            'kira_dosya_id' => $dosya->id,
            'tip' => 'ana',
            'ad_unvan' => $isyeri.' kiracısı',
        ]);
        $poligon = KiraPoligon::create([
            'ad' => $isyeri,
            'koordinat' => [
                'type' => 'Polygon',
                'coordinates' => [[
                    [$lng, $lat],
                    [$lng + 0.01, $lat],
                    [$lng + 0.01, $lat + 0.01],
                    [$lng, $lat + 0.01],
                    [$lng, $lat],
                ]],
            ],
        ]);
        KiraAlan::create([
            'kira_dosya_id' => $dosya->id,
            'poligon_id' => $poligon->id,
            'ad' => $isyeri,
            'is_kolu_id' => $isKoluId,
            'dolu_bos' => 'dolu',
        ]);

        return $dosya;
    }

    private function kullanici(): Kullanici
    {
        $rol = Rol::query()->where('kod', 'goruntuleyici')->firstOrFail();

        return Kullanici::factory()->create([
            'rol_id' => $rol->id,
            'aktif_mi' => true,
        ]);
    }
}
