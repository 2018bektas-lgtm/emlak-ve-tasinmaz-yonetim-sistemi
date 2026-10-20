<?php

namespace Tests\Feature;

use App\Models\Il;
use App\Models\Ilce;
use App\Models\Izin;
use App\Models\Kullanici;
use App\Models\Mahalle;
use App\Models\Rol;
use App\Models\Tasinmaz;
use App\Models\TasinmazHisse;
use Database\Seeders\YetkiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HisseListesiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(YetkiSeeder::class);
    }

    public function test_hisse_listesi_sayfalanir_ve_filtreler(): void
    {
        $kullanici = $this->kullanici('goruntuleyici');
        $aktif = $this->hisseOlustur('12', '34', ['hisse_no' => 7, 'hisse_durum' => 'aktif', 'edinme_sekli' => 'Satın alma']);
        $pasif = $this->hisseOlustur('88', '90', ['hisse_no' => 3, 'hisse_durum' => 'pasif', 'edinme_sekli' => 'Devir']);

        $this->actingAs($kullanici)
            ->get(route('panel.tasinmazlar.hisseler'))
            ->assertOk()
            ->assertSee('Hisseler', false)
            ->assertSee('data-hisse-id="'.$aktif->id.'"', false)
            ->assertSee('data-hisse-id="'.$pasif->id.'"', false)
            ->assertSee('tl-pagination', false)
            ->assertSee('Tümü', false)
            ->assertSee('200,00', false)
            ->assertSee('Aktif', false)
            ->assertSee('Pasif', false)
            ->assertSee('100,00', false)
            ->assertSee('1 hisse', false)
            ->assertSee('Gelişmiş', false)
            ->assertSee('Kolonları Yönet', false)
            ->assertSee('İndir', false)
            ->assertSee('data-kolon="hisse_m2"', false)
            ->assertSee('data-kolon="maliyet" class="is-gizli"', false);

        $this->actingAs($kullanici)
            ->get(route('panel.tasinmazlar.hisseler', ['durum' => 'aktif']))
            ->assertOk()
            ->assertSee('data-hisse-id="'.$aktif->id.'"', false)
            ->assertDontSee('data-hisse-id="'.$pasif->id.'"', false);

        $this->actingAs($kullanici)
            ->get(route('panel.tasinmazlar.hisseler', ['q' => '88']))
            ->assertOk()
            ->assertSee('data-hisse-id="'.$pasif->id.'"', false)
            ->assertDontSee('data-hisse-id="'.$aktif->id.'"', false);

        $this->actingAs($kullanici)
            ->get(route('panel.tasinmazlar.hisseler', ['ada' => '12', 'parsel' => '34']))
            ->assertOk()
            ->assertSee('data-hisse-id="'.$aktif->id.'"', false)
            ->assertDontSee('data-hisse-id="'.$pasif->id.'"', false)
            ->assertSee('100,00 <em>m²</em>', false)
            ->assertDontSee('200,00 <em>m²</em>', false)
            ->assertSee('0,00 <em>m²</em>', false);
    }

    public function test_ikinci_sayfa_ayri_kayit_getirir(): void
    {
        $ilk = null;
        for ($i = 1; $i <= 21; $i++) {
            $hisse = $this->hisseOlustur((string) $i, '1', ['hisse_no' => $i]);
            $ilk ??= $hisse;
        }

        $this->actingAs($this->kullanici('goruntuleyici'))
            ->get(route('panel.tasinmazlar.hisseler', ['page' => 2]))
            ->assertOk()
            ->assertSee('data-hisse-id="'.$ilk->id.'"', false)
            ->assertSee('page=1', false);
    }

    public function test_excel_filtreli_hisseleri_indirir(): void
    {
        $aktif = $this->hisseOlustur('12', '34', ['hisse_no' => 7, 'hisse_durum' => 'aktif']);
        $this->hisseOlustur('88', '90', ['hisse_no' => 3, 'hisse_durum' => 'pasif']);
        $kullanici = $this->kullanici('goruntuleyici');

        $yanit = $this->actingAs($kullanici)
            ->get(route('panel.tasinmazlar.hisseler.excel', ['q' => '88']));

        $yanit->assertOk();
        $yanit->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $sayfa = $this->excelSayfasi($yanit->streamedContent());

        $this->assertStringContainsString('1 kayıt', (string) $sayfa->getCell('A1')->getValue());
        $this->assertSame('88', (string) $this->excelHucre($sayfa, 'Ada', 3));
        $this->assertEquals(100, (float) $this->excelHucre($sayfa, 'Hisse m²', 3));
        $this->assertEquals(100, (float) $this->excelHucre($sayfa, 'Hisse m²', 4));

        $secili = $this->actingAs($kullanici)
            ->get(route('panel.tasinmazlar.hisseler.excel', ['ids' => $aktif->id]));
        $seciliSayfa = $this->excelSayfasi($secili->streamedContent());
        $this->assertStringContainsString('1 kayıt', (string) $seciliSayfa->getCell('A1')->getValue());
        $this->assertSame('12', (string) $this->excelHucre($seciliSayfa, 'Ada', 3));
    }

    public function test_gelismis_aralik_filtresi(): void
    {
        $kullanici = $this->kullanici('goruntuleyici');
        $aktif = $this->hisseOlustur('12', '34', ['hisse_durum' => 'aktif']);
        $pasif = $this->hisseOlustur('88', '90', ['hisse_durum' => 'pasif']);
        $ucuz = $this->hisseOlustur('1', '1', ['rayic_bedel' => 100]);
        $pahali = $this->hisseOlustur('2', '2', ['rayic_bedel' => 1250.5]);

        $this->actingAs($kullanici)
            ->get(route('panel.tasinmazlar.hisseler', ['hisse_min' => '50', 'hisse_max' => '100']))
            ->assertOk()
            ->assertSee('data-hisse-id="'.$aktif->id.'"', false)
            ->assertSee('data-hisse-id="'.$pasif->id.'"', false);

        $this->actingAs($kullanici)
            ->get(route('panel.tasinmazlar.hisseler', ['hisse_min' => '150']))
            ->assertOk()
            ->assertDontSee('data-hisse-id="'.$aktif->id.'"', false)
            ->assertDontSee('data-hisse-id="'.$pasif->id.'"', false)
            ->assertSee('0,00 <em>m²</em>', false);

        $this->actingAs($kullanici)
            ->get(route('panel.tasinmazlar.hisseler', ['rayic_min' => '1.250,50']))
            ->assertOk()
            ->assertSee('data-hisse-id="'.$pahali->id.'"', false)
            ->assertDontSee('data-hisse-id="'.$ucuz->id.'"', false);

        $this->actingAs($kullanici)
            ->get(route('panel.tasinmazlar.hisseler', ['alan_min' => '250']))
            ->assertOk()
            ->assertDontSee('data-hisse-id="'.$aktif->id.'"', false);

        $this->actingAs($kullanici)
            ->get(route('panel.tasinmazlar.hisseler', ['oran_min' => '60']))
            ->assertOk()
            ->assertDontSee('data-hisse-id="'.$aktif->id.'"', false)
            ->assertDontSee('data-hisse-id="'.$pasif->id.'"', false);

        $this->actingAs($kullanici)
            ->get(route('panel.tasinmazlar.hisseler', ['oran_max' => '50']))
            ->assertOk()
            ->assertSee('data-hisse-id="'.$aktif->id.'"', false);
    }

    public function test_gelismis_filtre_tum_alanlari_kapsar(): void
    {
        $kullanici = $this->kullanici('goruntuleyici');
        $a = $this->hisseOlustur('12', '34', [
            'hisse_no' => 7,
            'edinme_sekli' => 'Satın alma',
            'yevmiye_no' => 555,
            'edinme_tarihi' => '2024-03-01',
            'maliyet_bedeli' => 5000,
            'kayitlardan_cikis' => 'Terkin',
            'kayitlardan_cikistarihi' => '2024-06-01',
        ]);
        $b = $this->hisseOlustur('88', '90', [
            'hisse_no' => 3,
            'edinme_sekli' => 'Devir',
            'yevmiye_no' => 9,
            'edinme_tarihi' => '2020-01-01',
            'maliyet_bedeli' => 10,
        ]);

        $bak = function (array $sorgu, array $gorunen, array $gizli) use ($kullanici): void {
            $yanit = $this->actingAs($kullanici)
                ->get(route('panel.tasinmazlar.hisseler', $sorgu))
                ->assertOk();
            foreach ($gorunen as $id) {
                $yanit->assertSee('data-hisse-id="'.$id.'"', false);
            }
            foreach ($gizli as $id) {
                $yanit->assertDontSee('data-hisse-id="'.$id.'"', false);
            }
        };

        $bak(['nitelik' => 'Arsa'], [$a->id, $b->id], []);
        $bak(['nitelik' => 'Tarla'], [], [$a->id, $b->id]);
        $bak(['hisse_no' => '7'], [$a->id], [$b->id]);
        $bak(['edinme' => 'Satın'], [$a->id], [$b->id]);
        $bak(['yevmiye' => '555'], [$a->id], [$b->id]);
        $bak(['tarih_min' => '2024-01-01'], [$a->id], [$b->id]);
        $bak(['maliyet_min' => '1.000'], [$a->id], [$b->id]);
        $bak(['cikis' => 'Terkin'], [$a->id], [$b->id]);
        $bak(['cikis_tarih_min' => '2024-05-01'], [$a->id], [$b->id]);
        $bak(['adet_min' => '1'], [$a->id, $b->id], []);
        $bak(['pay_min' => '2'], [], [$a->id, $b->id]);

        $this->actingAs($kullanici)
            ->get(route('panel.tasinmazlar.hisseler'))
            ->assertOk()
            ->assertSee('Nitelik', false)
            ->assertSee('Edinme', false)
            ->assertSee('Maliyet', false)
            ->assertSee('Emlak', false)
            ->assertSee('İz', false)
            ->assertSee('>Pay<', false)
            ->assertSee('Payda', false)
            ->assertSee('Hisse adedi', false)
            ->assertSee('name="nitelik"', false)
            ->assertSee('name="hisse_no"', false)
            ->assertSee('name="edinme"', false)
            ->assertSee('name="yevmiye"', false)
            ->assertSee('name="cikis"', false)
            ->assertSee('name="pay_min"', false)
            ->assertSee('name="payda_min"', false)
            ->assertSee('name="adet_min"', false)
            ->assertSee('name="aktif_min"', false)
            ->assertSee('name="emlak_min"', false)
            ->assertSee('name="iz_min"', false)
            ->assertSee('name="tarih_min"', false)
            ->assertSee('name="cikis_tarih_max"', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertSee('id="hl-filtre-gelismis"', false);
    }

    public function test_secilen_kolonlar_excel_ve_pdf_ciktisina_yansir(): void
    {
        $this->hisseOlustur('88', '90');
        $kullanici = $this->kullanici('goruntuleyici');

        $this->actingAs($kullanici)
            ->postJson(route('panel.kolon-tercihi.kaydet'), [
                'tablo' => 'hisseler',
                'kolonlar' => ['ada'],
            ])
            ->assertOk()
            ->assertJsonPath('kolonlar', ['ada']);

        $sayfa = $this->excelSayfasi(
            $this->actingAs($kullanici)->get(route('panel.tasinmazlar.hisseler.excel'))->streamedContent()
        );
        $basliklar = [];
        for ($i = 1; $i <= 10; $i++) {
            $ad = (string) $sayfa->getCell([$i, 2])->getValue();
            if ($ad === '') {
                break;
            }
            $basliklar[] = $ad;
        }
        $this->assertSame(['Sıra', 'Ada'], $basliklar);
        $this->assertSame('88', (string) $sayfa->getCell('B3')->getValue());

        $this->actingAs($kullanici)
            ->get(route('panel.tasinmazlar.hisseler.pdf'))
            ->assertOk()
            ->assertSee('<th>Ada</th>', false)
            ->assertDontSee('Hisse m²', false);

        $this->actingAs($kullanici)
            ->get(route('panel.tasinmazlar.hisseler'))
            ->assertOk()
            ->assertSee('data-kolon="hisse_m2" class="is-gizli"', false)
            ->assertSee('data-kolon="ada"', false);
    }

    public function test_kml_parsel_geometrisini_tekrarsiz_indirir(): void
    {
        $bir = $this->hisseOlustur('12', '34');
        $bir->tasinmaz->hisseler()->create([
            'hisse_pay' => 1,
            'hisse_payda' => 4,
            'hisse_durum' => 'aktif',
        ]);
        $bir->tasinmaz->koordinat()->create(['lat' => 39.9, 'lng' => 32.8]);
        $iki = $this->hisseOlustur('88', '90');
        $iki->tasinmaz->koordinat()->create(['lat' => 41.0, 'lng' => 29.1]);
        $kullanici = $this->kullanici('goruntuleyici');

        $tum = $this->actingAs($kullanici)->get(route('panel.tasinmazlar.hisseler.kml'));
        $tum->assertOk();
        $this->assertStringContainsString('kml', (string) $tum->headers->get('content-type'));
        $this->assertSame(2, substr_count($tum->getContent(), '<Placemark>'));

        $secili = $this->actingAs($kullanici)
            ->get(route('panel.tasinmazlar.hisseler.kml', ['ids' => $bir->id]));
        $icerik = $secili->getContent();
        $this->assertSame(1, substr_count($icerik, '<Placemark>'));
        $this->assertStringContainsString('32.8', $icerik);
        $this->assertStringNotContainsString('29.1', $icerik);
    }

    public function test_izinsiz_kullanici_hisse_listesini_goremez(): void
    {
        $rol = Rol::query()->create([
            'kod' => 'tasinmaz-sadece',
            'ad' => 'Yalnız taşınmaz',
            'sistem_mi' => false,
            'sira' => 9,
        ]);
        $rol->izinler()->sync([
            Izin::query()->where('kod', 'tasinmaz.goruntule')->value('id'),
        ]);
        $kullanici = Kullanici::factory()->create([
            'rol_id' => $rol->id,
            'aktif_mi' => true,
        ]);

        $this->actingAs($kullanici)
            ->get(route('panel.tasinmazlar.hisseler'))
            ->assertForbidden();
    }

    private function excelSayfasi(string $icerik): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        $dosya = tempnam(sys_get_temp_dir(), 'hisse');
        file_put_contents($dosya, $icerik);
        $sayfa = \PhpOffice\PhpSpreadsheet\IOFactory::load($dosya)->getActiveSheet();
        @unlink($dosya);

        return $sayfa;
    }

    private function excelHucre(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sayfa, string $baslik, int $satir): mixed
    {
        $kolon = null;
        for ($i = 1; $i <= 40; $i++) {
            $ad = (string) $sayfa->getCell([$i, 2])->getValue();
            if ($ad === '') {
                break;
            }
            if ($ad === $baslik) {
                $kolon = $i;
            }
        }
        $this->assertNotNull($kolon, $baslik.' kolonu yok');

        return $sayfa->getCell([$kolon, $satir])->getValue();
    }

    private function kullanici(string $rolKodu): Kullanici
    {
        $rol = Rol::query()->where('kod', $rolKodu)->firstOrFail();

        return Kullanici::factory()->create([
            'rol_id' => $rol->id,
            'aktif_mi' => true,
        ]);
    }

    /** @param array<string, mixed> $hisse */
    private function hisseOlustur(string $ada, string $parsel, array $hisse = []): TasinmazHisse
    {
        static $sira = 1;

        $il = Il::query()->first() ?? Il::query()->create(['tkgm_id' => 6, 'ad' => 'Ankara']);
        $ilce = Ilce::query()->first() ?? Ilce::query()->create([
            'tkgm_id' => 1234,
            'il_id' => $il->id,
            'ad' => 'Çankaya',
        ]);
        $mahalle = Mahalle::query()->create([
            'tkgm_id' => 9100 + $sira,
            'ilce_id' => $ilce->id,
            'ad' => 'Hisse Mahalle '.$sira,
        ]);
        $sira++;

        $tasinmaz = Tasinmaz::query()->create([
            'il_id' => $il->id,
            'ilce_id' => $ilce->id,
            'mahalle_id' => $mahalle->id,
            'ada' => $ada,
            'parsel' => $parsel,
            'alan' => 200,
            'nitelik' => 'Arsa',
            'uzeri_bina_var_mi' => false,
        ]);

        return $tasinmaz->hisseler()->create(array_merge([
            'hisse_pay' => 1,
            'hisse_payda' => 2,
            'hisse_durum' => 'aktif',
        ], $hisse));
    }
}
