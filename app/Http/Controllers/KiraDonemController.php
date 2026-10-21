<?php

namespace App\Http\Controllers;

use App\Models\KiraDosyasi;
use App\Models\KiraSozlesme;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Kiracılık dönemleri.
 *
 * Bir dosya numarasında (örn. K0050) yıllar içinde birbiri ardına farklı
 * kiracılar olur: her kiracı kendi sözleşme dönemidir. Dosya kartı bu
 * dönemleri listeler, buradan her dönemin kendi kartına girilir.
 *
 * Kiracı değişiminde yeni dosya açılmaz: aynı dosya numarasına yeni bir dönem
 * eklenir, önceki dönem kapatılır.
 */
class KiraDonemController extends Controller
{
    /** Dönem kartı: o kiracının sözleşmesi, bedelleri ve belgeleri. */
    public function detay(int $dosyaId, int $donemId): View
    {
        $dosya = KiraDosyasi::with(['tasinmaz.ilce', 'tasinmaz.mahalle', 'isKolu'])->findOrFail($dosyaId);
        $donem = KiraSozlesme::with(['kiracilar', 'bedeller', 'belgeler.yukleyen'])
            ->where('kira_dosya_id', $dosya->id)
            ->findOrFail($donemId);

        $tumDonemler = KiraSozlesme::where('kira_dosya_id', $dosya->id)
            ->with('anaKiraci:id,kira_sozlesme_id,ad_unvan')
            ->orderByDesc('baslangic_tarihi')->orderByDesc('id')
            ->get();

        return view('panel.kira.donem.detay', [
            'dosya' => $dosya,
            'donem' => $donem,
            'tumDonemler' => $tumDonemler,
            'sira' => $tumDonemler->search(fn ($d) => $d->id === $donem->id),
        ]);
    }

    /** Kiracı değişikliği formu: aynı dosyaya yeni kiracı. */
    public function olustur(int $dosyaId): View
    {
        $dosya = KiraDosyasi::with(['tasinmaz', 'sozlesmeler.anaKiraci'])->findOrFail($dosyaId);

        return view('panel.kira.donem.form', [
            'dosya' => $dosya,
            'oncekiDonem' => $dosya->sozlesmeler->sortByDesc('bitis_tarihi')->first(),
            // Süresi dolmamış bir kira varsa form onay ister
            'devamEden' => $this->devamEdenKira($dosya),
        ]);
    }

    /**
     * Süresi henüz dolmamış, yürürlükteki kira.
     *
     * Süresi bitmiş dönem sessizce geçmişe düşer; bitmemiş bir kira varsa
     * yeni kiracı onun sözleşmesini erken kapatacağı için onay istenir.
     */
    private function devamEdenKira(KiraDosyasi $dosya): ?KiraSozlesme
    {
        $bugun = now()->startOfDay();

        return KiraSozlesme::where('kira_dosya_id', $dosya->id)
            ->where('aktif_mi', true)
            ->where(fn ($q) => $q->whereNull('bitis_tarihi')->orWhereDate('bitis_tarihi', '>=', $bugun))
            ->with('anaKiraci')
            ->orderByDesc('bitis_tarihi')
            ->first();
    }

    /**
     * Yeni dönem kaydı. Önceki yürürlükteki dönem kapatılır — bir dosyada aynı
     * anda tek dönem yürürlükte olur.
     */
    public function kaydet(Request $request, int $dosyaId): RedirectResponse
    {
        $dosya = KiraDosyasi::findOrFail($dosyaId);
        $veri = $this->dogrula($request);

        // Süresi dolmamış kira varsa, erken kapatılacağı için açık onay şart.
        // Kontrol veritabanından yapılır; formdaki alan tek başına yeterli değil.
        $devamEden = $this->devamEdenKira($dosya);
        if ($devamEden && ! $request->boolean('devam_onayi')) {
            return back()->withInput()->withErrors([
                'devam_onayi' => 'Bu dosyada süresi dolmamış bir kira var ('
                    .($devamEden->anaKiraci?->ad_unvan ?: 'kiracı girilmemiş').' · bitiş '
                    .($devamEden->bitis_tarihi?->format('d.m.Y') ?: 'belirsiz')
                    .'). Devam etmek için onay kutusunu işaretleyin.',
            ]);
        }

        $donem = DB::transaction(function () use ($dosya, $veri, $request) {
            $yeniBaslangic = Carbon::parse($veri['baslangic_tarihi'])->startOfDay();

            // Yürürlükteki dönemler kapanır. Bitiş tarihi yalnızca yeni
            // dönemden ÖNCE başlamış dönemlerde kısaltılır; sonra başlayan bir
            // dönemde kısaltmak bitişi kendi başlangıcından önceye düşürürdü.
            $oncekiler = KiraSozlesme::where('kira_dosya_id', $dosya->id)->where('aktif_mi', true)->get();
            foreach ($oncekiler as $onceki) {
                $guncel = ['aktif_mi' => false];
                $kisaltilabilir = $onceki->baslangic_tarihi?->lt($yeniBaslangic)
                    && (! $onceki->bitis_tarihi || $onceki->bitis_tarihi->gte($yeniBaslangic));

                if ($kisaltilabilir) {
                    $guncel['bitis_tarihi'] = $yeniBaslangic->copy()->subDay()->toDateString();
                }
                $onceki->update($guncel);
            }

            $donem = KiraSozlesme::create([
                'kira_dosya_id' => $dosya->id,
                'sozlesme_no' => $veri['sozlesme_no'] ?? null,
                'baslangic_tarihi' => $veri['baslangic_tarihi'],
                'bitis_tarihi' => $veri['bitis_tarihi'],
                'sure_ay' => $veri['sure_ay'] ?? null,
                'taksit_sayisi' => $veri['taksit_sayisi'] ?? null,
                'odeme_sekli' => $veri['odeme_sekli'] ?? null,
                'sozlesme_tarihi' => $veri['sozlesme_tarihi'] ?? null,
                'noter_tarihi' => $veri['noter_tarihi'] ?? null,
                'yevmiye_no' => $veri['yevmiye_no'] ?? null,
                'kdv_orani' => $veri['kdv_orani'] ?? null,
                'kdv_dahil_mi' => $request->boolean('kdv_dahil_mi'),
                'artirma_yontemi' => $veri['artirma_yontemi'] ?? null,
                'aktif_mi' => true,
                'aciklama' => $veri['aciklama'] ?? null,
            ]);

            // Yeni dönemin kiracısı — dosyaya değil döneme bağlanır
            $donem->kiracilar()->create([
                'kira_dosya_id' => $dosya->id,
                'tip' => 'ana',
                'ad_unvan' => $veri['kiraci_ad_unvan'],
                'tc_vergi_no' => $veri['kiraci_tc_vergi_no'] ?? null,
                'telefon' => $veri['kiraci_telefon'] ?? null,
                'eposta' => $veri['kiraci_eposta'] ?? null,
                'adres' => $veri['kiraci_adres'] ?? null,
                'iban' => $veri['kiraci_iban'] ?? null,
                'avukat' => $veri['kiraci_avukat'] ?? null,
            ]);

            foreach ($this->bedelSatirlari($veri['bedeller'] ?? [], $veri['baslangic_tarihi'], $veri['bitis_tarihi']) as $satir) {
                $donem->bedeller()->create($satir);
            }

            // Dosya yeni kiracıyla dolu sayılır
            $dosya->update(['sozlesme_aktif_mi' => true, 'dolu_bos' => 'dolu']);

            return $donem;
        });

        return redirect()->route('panel.kira.donem.detay', [$dosya->id, $donem->id])
            ->with('basari', 'Yeni kiracılık dönemi eklendi, önceki dönem kapatıldı.');
    }

    /** @return array<string, mixed> */
    private function dogrula(Request $request): array
    {
        return $request->validate([
            'kiraci_ad_unvan' => ['required', 'string', 'max:250'],
            'kiraci_tc_vergi_no' => ['nullable', 'string', 'max:20'],
            'kiraci_telefon' => ['nullable', 'string', 'max:30'],
            'kiraci_eposta' => ['nullable', 'email', 'max:150'],
            'kiraci_adres' => ['nullable', 'string', 'max:1000'],
            'kiraci_iban' => ['nullable', 'string', 'max:34'],
            'kiraci_avukat' => ['nullable', 'string', 'max:200'],

            'sozlesme_no' => ['nullable', 'string', 'max:50'],
            'baslangic_tarihi' => ['required', 'date'],
            'bitis_tarihi' => ['required', 'date', 'after:baslangic_tarihi'],
            'sure_ay' => ['nullable', 'integer', 'min:1', 'max:1200'],
            'taksit_sayisi' => ['nullable', 'integer', 'min:1', 'max:255'],
            'odeme_sekli' => ['nullable', 'string', 'max:100'],
            'sozlesme_tarihi' => ['nullable', 'date'],
            'noter_tarihi' => ['nullable', 'date'],
            'yevmiye_no' => ['nullable', 'string', 'max:50'],
            'kdv_orani' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'artirma_yontemi' => ['nullable', 'string', 'max:100'],
            'aciklama' => ['nullable', 'string', 'max:5000'],

            'bedeller' => ['array', 'max:30'],
            'bedeller.*.yillik_bedel' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'bedeller.*.donem_baslangic' => ['nullable', 'date'],
            'bedeller.*.donem_bitis' => ['nullable', 'date'],
            'bedeller.*.tufe_orani' => ['nullable', 'numeric', 'min:-100', 'max:1000'],
        ], [
            'kiraci_ad_unvan.required' => 'Yeni dönemin kiracısı girilmeli.',
            'bitis_tarihi.after' => 'Bitiş tarihi başlangıçtan sonra olmalı.',
        ]);
    }

    /**
     * Bedel satırları: tarih verilmeyen satırlar sözleşme başlangıcından
     * itibaren yıl yıl türetilir. (KiraController'daki ile aynı sözleşme.)
     *
     * @param  array<int, array<string, mixed>>  $satirlar
     * @return array<int, array<string, mixed>>
     */
    private function bedelSatirlari(array $satirlar, string $sozlesmeBaslangic, string $sozlesmeBitis): array
    {
        $dolu = array_values(array_filter($satirlar, fn ($s) => filled($s['yillik_bedel'] ?? null)));
        $ilk = Carbon::parse($sozlesmeBaslangic)->startOfDay();
        $son = Carbon::parse($sozlesmeBitis)->startOfDay();

        return array_map(function ($s, $i) use ($ilk, $son) {
            $baslangic = $s['donem_baslangic'] ?? null;
            $bitis = $s['donem_bitis'] ?? null;

            if (blank($baslangic) && blank($bitis)) {
                $yilBasi = $ilk->copy()->addYearsNoOverflow($i);
                if ($yilBasi->lte($son)) {
                    $baslangic = $yilBasi->toDateString();
                    $bitis = $yilBasi->copy()->addYearNoOverflow()->subDay()->min($son)->toDateString();
                }
            }

            return [
                'yil_sirasi' => $i + 1,
                'donem_baslangic' => $baslangic ?: null,
                'donem_bitis' => $bitis ?: null,
                'yillik_bedel' => $s['yillik_bedel'],
                'tufe_orani' => $s['tufe_orani'] ?? null,
            ];
        }, $dolu, array_keys($dolu));
    }
}
