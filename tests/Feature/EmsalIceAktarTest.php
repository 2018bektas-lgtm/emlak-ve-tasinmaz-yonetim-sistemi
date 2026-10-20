<?php

namespace Tests\Feature;

use App\Models\Emsal;
use App\Models\EmsalBagimsizBolum;
use App\Models\Il;
use App\Models\Ilce;
use App\Models\ImarDurumu;
use App\Models\Kullanici;
use App\Models\Mahalle;
use App\Models\Rol;
use Database\Seeders\YetkiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class EmsalIceAktarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(YetkiSeeder::class);
    }

    public function test_sablon_indirilir_ve_satir_kayda_donusur(): void
    {
        $kullanici = $this->kullanici('kullanici');
        $konum = $this->konum();
        ImarDurumu::query()->create(['ad' => 'Konut', 'aktif_mi' => true]);

        ImarDurumu::query()->create(['ad' => 'Pasif Alan', 'aktif_mi' => false]);

        $this->actingAs($kullanici)
            ->get(route('panel.emsal.ice-aktar'))
            ->assertOk()
            ->assertSee('Dosyayı buraya bırakın', false)
            ->assertSee('Şablonu indir', false)
            ->assertSee('Bağımsız bölüm', false)
            ->assertSee('listeden seçilir', false)
            ->assertSee('ad olarak yazılır', false)
            ->assertSee('>liste</em>', false);

        $sablon = $this->actingAs($kullanici)->get(route('panel.emsal.sablon'));
        $sablon->assertOk();
        $kitap = $this->excelKitap($sablon->streamedContent());
        $sayfa = $kitap->getActiveSheet();
        $this->assertSame('Emsaller', $sayfa->getTitle());
        $this->assertSame('Tür *', (string) $sayfa->getCell('A1')->getValue());
        $this->assertSame('İl *', (string) $sayfa->getCell('B1')->getValue());
        $this->assertSame('İlçe *', (string) $sayfa->getCell('C1')->getValue());
        $this->assertSame('Mahalle *', (string) $sayfa->getCell('D1')->getValue());
        $this->assertSame('Birim Fiyat (TL/m²) *', (string) $sayfa->getCell('L1')->getValue());

        foreach (['A', 'H'] as $harf) {
            $dogrulama = $sayfa->getDataValidation($harf.'2');
            $this->assertSame(DataValidation::TYPE_LIST, $dogrulama->getType());
            $this->assertTrue($dogrulama->getShowDropDown());
            $this->assertTrue($dogrulama->getAllowBlank());
        }
        $this->assertStringContainsString('Listeler!$A$', $sayfa->getDataValidation('A2')->getFormula1());
        $this->assertStringContainsString('Listeler!$B$', $sayfa->getDataValidation('H2')->getFormula1());
        $this->assertFalse($sayfa->dataValidationExists('B2'));
        $this->assertFalse($sayfa->dataValidationExists('C2'));
        $this->assertFalse($sayfa->dataValidationExists('D2'));
        $this->assertFalse($sayfa->dataValidationExists('P2'));
        $this->assertFalse($sayfa->dataValidationExists('Z2'));

        $liste = $kitap->getSheetByName('Listeler');
        $this->assertInstanceOf(Worksheet::class, $liste);
        $this->assertSame(Worksheet::SHEETSTATE_HIDDEN, $liste->getSheetState());
        $this->assertSame(
            ['Encümen Kararı', 'Resmî Senet', 'Mahkeme Kararı'],
            [(string) $liste->getCell('A1')->getValue(), (string) $liste->getCell('A2')->getValue(), (string) $liste->getCell('A3')->getValue()]
        );
        $this->assertSame('Konut', (string) $liste->getCell('B1')->getValue());
        $this->assertSame('', (string) $liste->getCell('B2')->getValue());
        $this->assertSame('', (string) $liste->getCell('C1')->getValue());

        Http::fake();
        $dosya = $this->excelDosyasi([
            ['Encümen Kararı', 'Ankara', 'Çankaya', $konum['mahalle']->ad, '12', '34', '200', 'Konut', '1,4', '3/5', '15.03.2024', '1.250,50', '2024/15', 'K-9', '', '', '1/2', '', '', 'Bahçe katı', '3', 'Zemin', 'Güney', '80', 'Mesken', 'Boş'],
        ]);

        $this->actingAs($kullanici)
            ->post(route('panel.emsal.ice-aktar.yukle'), ['dosya' => $dosya])
            ->assertRedirect(route('panel.emsal.ice-aktar'))
            ->assertSessionHas('basari');

        Http::assertNothingSent();

        $emsal = Emsal::query()->first();
        $this->assertNotNull($emsal);
        $this->assertSame('encumen', $emsal->tip->value);
        $this->assertSame('2024/15', $emsal->karar_no);
        $this->assertSame('K-9', $emsal->kayit_no);
        $this->assertNull($emsal->yevmiye_no);
        $this->assertSame('2024-03-15', $emsal->tarih->toDateString());
        $this->assertEquals(1250.5, (float) $emsal->birim_fiyat);
        $this->assertSame($konum['mahalle']->id, $emsal->parsel->mahalle_id);
        $this->assertSame('12', $emsal->parsel->ada);
        $this->assertEquals(200, (float) $emsal->parsel->alan);
        $this->assertEquals(1.4, (float) $emsal->parsel->emsal);
        $this->assertSame('Konut', $emsal->parsel->imarDurumu->ad);
        $this->assertSame(1, EmsalBagimsizBolum::query()->count());
        $this->assertSame('3', EmsalBagimsizBolum::query()->value('bb_no'));

        $this->actingAs($kullanici)
            ->post(route('panel.emsal.ice-aktar.yukle'), ['dosya' => $this->excelDosyasi([
                ['Encümen Kararı', 'Ankara', 'Çankaya', $konum['mahalle']->ad, '12', '34', '', '', '', '', '15.03.2024', '1250,50', '2024/15'],
            ])])
            ->assertRedirect(route('panel.emsal.ice-aktar'))
            ->assertSessionHas('basari');

        $this->assertSame(1, Emsal::query()->count());
    }

    public function test_ayni_adli_ilce_il_kolonuyla_ayrilir(): void
    {
        $kullanici = $this->kullanici('kullanici');
        $ankara = Il::query()->create(['tkgm_id' => 6, 'ad' => 'Ankara']);
        $istanbul = Il::query()->create(['tkgm_id' => 34, 'ad' => 'İstanbul']);
        $cankayaAnkara = Ilce::query()->create(['tkgm_id' => 1234, 'il_id' => $ankara->id, 'ad' => 'Çankaya']);
        $cankayaIstanbul = Ilce::query()->create(['tkgm_id' => 1235, 'il_id' => $istanbul->id, 'ad' => 'Çankaya']);
        $mahalle = Mahalle::query()->create(['tkgm_id' => 9101, 'ilce_id' => $cankayaAnkara->id, 'ad' => 'A Mahallesi']);
        Mahalle::query()->create(['tkgm_id' => 9102, 'ilce_id' => $cankayaIstanbul->id, 'ad' => 'B Mahallesi']);

        Http::fake();
        $this->actingAs($kullanici)
            ->post(route('panel.emsal.ice-aktar.yukle'), ['dosya' => $this->excelDosyasi([
                ['Resmî Senet', 'Ankara', 'Çankaya', 'A Mahallesi', '1', '2', '', '', '', '', '01.01.2024', '100', '', '', 'Y-1'],
            ])])
            ->assertRedirect(route('panel.emsal.ice-aktar'))
            ->assertSessionHas('basari');
        Http::assertNothingSent();

        $emsal = Emsal::query()->first();
        $this->assertNotNull($emsal);
        $this->assertSame($mahalle->id, $emsal->parsel->mahalle_id);
        $this->assertSame($cankayaAnkara->id, $emsal->parsel->ilce_id);
        $this->assertSame($ankara->id, $emsal->parsel->il_id);
        $this->assertNull($emsal->parsel->koordinat);

        $this->actingAs($kullanici)
            ->post(route('panel.emsal.ice-aktar.yukle'), ['dosya' => $this->excelDosyasi([
                ['Resmî Senet', 'İstanbul', 'Çankaya', 'A Mahallesi', '3', '4', '', '', '', '', '02.01.2024', '200'],
            ])])
            ->assertRedirect(route('panel.emsal.ice-aktar'))
            ->assertSessionHas('hata');

        $this->assertSame(1, Emsal::query()->count());
        $this->assertStringContainsString('Mahalle bulunamadı', session('import_hatalari')[0]);
    }

    public function test_bilinmeyen_mahalle_satiri_kayit_acmaz(): void
    {
        $kullanici = $this->kullanici('kullanici');
        $this->konum();

        $this->actingAs($kullanici)
            ->post(route('panel.emsal.ice-aktar.yukle'), ['dosya' => $this->excelDosyasi([
                ['Resmî Senet', 'Ankara', 'Çankaya', 'Olmayan Mahalle', '1', '1', '', '', '', '', '01.01.2024', '100', '', '', '555'],
            ])])
            ->assertRedirect(route('panel.emsal.ice-aktar'))
            ->assertSessionHas('hata')
            ->assertSessionHas('import_hatalari');

        $this->assertSame(0, Emsal::query()->count());
        $this->assertStringContainsString('Satır 2', session('import_hatalari')[0]);
    }

    public function test_olusturma_yetkisi_olmayan_ice_aktaramaz(): void
    {
        $this->actingAs($this->kullanici('goruntuleyici'))
            ->get(route('panel.emsal.ice-aktar'))
            ->assertForbidden();

        $this->actingAs($this->kullanici('goruntuleyici'))
            ->get(route('panel.emsal.index'))
            ->assertOk()
            ->assertDontSee(route('panel.emsal.ice-aktar'), false);
    }

    public function test_olusturan_listede_ice_aktar_baglantisini_gorur(): void
    {
        $this->actingAs($this->kullanici('kullanici'))
            ->get(route('panel.emsal.index'))
            ->assertOk()
            ->assertSee(route('panel.emsal.ice-aktar'), false)
            ->assertSee('app-nav-parent is-open', false)
            ->assertSee('app-nav-subgroup is-open', false)
            ->assertSee('>Değerleme</span>', false)
            ->assertSee('>Emsal</span>', false);
    }

    private function kullanici(string $rolKodu): Kullanici
    {
        $rol = Rol::query()->where('kod', $rolKodu)->firstOrFail();

        return Kullanici::factory()->create([
            'rol_id' => $rol->id,
            'aktif_mi' => true,
        ]);
    }

    /** @return array{mahalle: Mahalle} */
    private function konum(): array
    {
        $il = Il::query()->create(['tkgm_id' => 6, 'ad' => 'Ankara']);
        $ilce = Ilce::query()->create(['tkgm_id' => 1234, 'il_id' => $il->id, 'ad' => 'Çankaya']);
        $mahalle = Mahalle::query()->create([
            'tkgm_id' => 9101,
            'ilce_id' => $ilce->id,
            'ad' => 'Emsal Mahallesi',
        ]);

        return ['mahalle' => $mahalle];
    }

    /** @param  array<int, array<int, mixed>>  $satirlar */
    private function excelDosyasi(array $satirlar): UploadedFile
    {
        $ss = new Spreadsheet;
        $sh = $ss->getActiveSheet();
        $sh->fromArray(array_merge([['başlık']], $satirlar), null, 'A1');
        $yol = tempnam(sys_get_temp_dir(), 'emsal').'.xlsx';
        (new Xlsx($ss))->save($yol);

        return new UploadedFile($yol, 'emsaller.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function excelKitap(string $icerik): Spreadsheet
    {
        $dosya = tempnam(sys_get_temp_dir(), 'emsal');
        file_put_contents($dosya, $icerik);
        $kitap = IOFactory::load($dosya);
        @unlink($dosya);

        return $kitap;
    }
}
