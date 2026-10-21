<?php

namespace Tests\Feature;

use App\Models\KiraDosyasi;
use App\Models\Kullanici;
use App\Models\Rol;
use Database\Seeders\YetkiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Kiracı sözleşme dönemine bağlıdır: dosya formu yalnız yürürlükteki dönemin
 * kiracısını gösterir ve değiştirir, eski dönemlerin kiracıları geçmiş olarak kalır.
 */
class KiraDonemKiraciTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(YetkiSeeder::class);
        $this->actingAs(Kullanici::factory()->create([
            'rol_id' => Rol::query()->where('kod', 'admin')->firstOrFail()->id,
            'aktif_mi' => true,
        ]));
    }

    public function test_yeni_dosyanin_kiracisi_ilk_donemine_baglanir(): void
    {
        $this->post(route('panel.kira.alanlar.kaydet'), $this->dosyaVerisi())->assertRedirect();

        $this->assertDatabaseHas('kira_kiracilari', [
            'ad_unvan' => 'Eski Kiracı',
            'kira_sozlesme_id' => DB::table('kira_sozlesmeleri')->value('id'),
        ]);
    }

    public function test_dosya_duzenlemesi_eski_donemin_kiracisina_dokunmaz(): void
    {
        $dosya = $this->ikiDonemliDosya();

        // Form yürürlükteki dönemin kiracısını gösterir
        $this->get(route('panel.kira.duzenle', $dosya->id))
            ->assertOk()
            ->assertSee('Yeni Kiracı', false);

        $this->put(route('panel.kira.guncelle', $dosya->id), $this->dosyaVerisi([
            'kiraci_ad_unvan' => 'Yeni Kiracı',
            'kiraci_telefon' => '5551112233',
            'baslangic_tarihi' => now()->toDateString(),
            'bitis_tarihi' => now()->addYear()->toDateString(),
        ]))->assertRedirect(route('panel.kira.detay', $dosya->id));

        $this->assertSame(
            [['Eski Kiracı', null], ['Yeni Kiracı', '5551112233']],
            DB::table('kira_kiracilari')->orderBy('id')->get(['ad_unvan', 'telefon'])
                ->map(fn ($k) => [$k->ad_unvan, $k->telefon])->all()
        );
    }

    public function test_dosya_bos_yapilinca_yalniz_yururlukteki_kiraci_kalkar(): void
    {
        $dosya = $this->ikiDonemliDosya();

        $this->put(route('panel.kira.guncelle', $dosya->id), $this->dosyaVerisi([
            'dolu_bos' => 'bos',
            'kiraci_ad_unvan' => '',
            'baslangic_tarihi' => now()->toDateString(),
            'bitis_tarihi' => now()->addYear()->toDateString(),
        ]))->assertRedirect();

        $this->assertSame(['Eski Kiracı'], DB::table('kira_kiracilari')->pluck('ad_unvan')->all());
    }

    public function test_donem_formu_ters_bedel_donemini_reddeder(): void
    {
        $this->post(route('panel.kira.alanlar.kaydet'), $this->dosyaVerisi());
        $dosya = KiraDosyasi::firstOrFail();

        $this->post(route('panel.kira.donem.kaydet', $dosya->id), [
            'kiraci_ad_unvan' => 'Yeni Kiracı',
            'baslangic_tarihi' => now()->toDateString(),
            'bitis_tarihi' => now()->addYear()->toDateString(),
            'bedeller' => [['yillik_bedel' => '1', 'donem_baslangic' => '2030-05-01', 'donem_bitis' => '2030-01-01']],
        ])->assertSessionHasErrors('bedeller.0.donem_bitis');

        $this->assertSame(1, DB::table('kira_sozlesmeleri')->count());
    }

    public function test_belge_adi_bos_birakilinca_dosya_adi_kullanilir(): void
    {
        Storage::fake('public');
        $this->post(route('panel.kira.alanlar.kaydet'), $this->dosyaVerisi());
        $dosya = KiraDosyasi::firstOrFail();

        $this->post(route('panel.kira.belge.yukle'), [
            'kira_dosya_id' => $dosya->id,
            'tur' => 'kira_sozlesmesi',
            'dosya' => UploadedFile::fake()->create('sozlesme-2026.pdf', 40, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('kira_belgeleri', ['kira_dosya_id' => $dosya->id, 'ad' => 'sozlesme-2026']);
    }

    /** Süresi dolmuş ilk dönem + yürürlükteki ikinci dönem. */
    private function ikiDonemliDosya(): KiraDosyasi
    {
        $this->post(route('panel.kira.alanlar.kaydet'), $this->dosyaVerisi());
        $dosya = KiraDosyasi::firstOrFail();

        $this->post(route('panel.kira.donem.kaydet', $dosya->id), [
            'kiraci_ad_unvan' => 'Yeni Kiracı',
            'baslangic_tarihi' => now()->toDateString(),
            'bitis_tarihi' => now()->addYear()->toDateString(),
            'bedeller' => [['yillik_bedel' => '200']],
        ])->assertSessionHasNoErrors();

        return $dosya;
    }

    /** @return array<string, mixed> */
    private function dosyaVerisi(array $ek = []): array
    {
        return array_replace([
            'dosya_no' => 'K-0001',
            'dolu_bos' => 'dolu',
            'ihale_turu' => 'ihaleli',
            'kiraci_ad_unvan' => 'Eski Kiracı',
            'baslangic_tarihi' => now()->subYears(2)->toDateString(),
            'bitis_tarihi' => now()->subMonth()->toDateString(),
            'sozlesme_aktif_mi' => '1',
            'bedeller' => [['yillik_bedel' => '100']],
        ], $ek);
    }
}
