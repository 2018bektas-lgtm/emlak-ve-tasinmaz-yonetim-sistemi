<?php

namespace App\Http\Controllers;

use App\Models\KiraDosyasi;
use App\Models\KiraSozlesme;
use App\Support\KiraKayit;
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
            'devamEden' => KiraKayit::devamEdenKira($dosya),
        ]);
    }

    /**
     * Yeni dönem kaydı. Önceki yürürlükteki dönem kapatılır — bir dosyada aynı
     * anda tek dönem yürürlükte olur.
     */
    public function kaydet(Request $request, int $dosyaId): RedirectResponse
    {
        $dosya = KiraDosyasi::findOrFail($dosyaId);
        $veri = $request->validate(KiraKayit::donemKurallari() + ['aciklama' => ['nullable', 'string', 'max:5000']], KiraKayit::mesajlar());

        // Süresi dolmamış kira varsa, erken kapatılacağı için açık onay şart.
        // Kontrol veritabanından yapılır; formdaki alan tek başına yeterli değil.
        $devamEden = KiraKayit::devamEdenKira($dosya);
        if ($devamEden && ! $request->boolean('devam_onayi')) {
            return back()->withInput()->withErrors(['devam_onayi' => KiraKayit::devamEdenUyarisi($devamEden, $dosya)]);
        }

        $donem = DB::transaction(function () use ($dosya, $veri, $request) {
            $donem = KiraKayit::yeniDonem($dosya, $veri, $request);
            if (filled($veri['aciklama'] ?? null)) {
                $donem->update(['aciklama' => $veri['aciklama']]);
            }
            $dosya->alanlariEsitle();

            return $donem;
        });

        return redirect()->route('panel.kira.donem.detay', [$dosya->id, $donem->id])
            ->with('basari', 'Yeni kiracılık dönemi eklendi, önceki dönem kapatıldı.');
    }
}
