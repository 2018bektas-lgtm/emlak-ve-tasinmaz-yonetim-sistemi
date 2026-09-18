<?php

namespace App\Http\Controllers;

use App\Models\Il;
use App\Models\Ilce;
use App\Models\Kullanici;
use App\Models\Lojman;
use App\Models\LojmanBasvuru;
use App\Models\LojmanEvrak;
use App\Models\LojmanTahsis;
use App\Models\Mahalle;
use App\Models\Tasinmaz;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Lojman modülü — sade sürüm.
 *  - Lojman (fiziksel birim, taşınmaza bağlı)
 *  - Başvuru (personel bilgileriyle)
 *  - Tahsis (başvuruya veya doğrudan lojmana)
 *  - Evrak (başvuru/lojman kategorili dosya ekleri)
 *
 * Aynı lojmana zaman içinde birden fazla tahsis yapılabilir.
 * "Aktif" tahsis: bitis_tarihi null veya bugün/gelecek.
 */
class LojmanController extends Controller
{
    // ============ LOJMAN ============

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $ilId = $request->query('il_id');
        $ilceId = $request->query('ilce_id');
        $mahalleId = $request->query('mahalle_id');
        $durum = $request->query('durum');
        $tip = trim((string) $request->query('tip', ''));

        $lojmanlar = Lojman::query()
            ->mudurlukKapsami()
            ->with(['tasinmaz.il:id,ad', 'tasinmaz.ilce:id,ad', 'tasinmaz.mahalle:id,ad'])
            ->withCount(['basvurular', 'tahsisler'])
            ->when($q !== '', function ($w) use ($q) {
                $w->where(function ($x) use ($q) {
                    $x->where('ad', 'like', "%{$q}%")
                        ->orWhere('blok', 'like', "%{$q}%")
                        ->orWhere('daire_no', 'like', "%{$q}%");
                });
            })
            ->when($durum, fn ($w) => $w->where('durum', $durum))
            ->when($tip !== '', fn ($w) => $w->where('tip', 'like', "%{$tip}%"))
            ->when($ilId || $ilceId || $mahalleId, function ($w) use ($ilId, $ilceId, $mahalleId) {
                $w->whereHas('tasinmaz', function ($t) use ($ilId, $ilceId, $mahalleId) {
                    $t->when($ilId, fn ($s) => $s->where('il_id', $ilId))
                        ->when($ilceId, fn ($s) => $s->where('ilce_id', $ilceId))
                        ->when($mahalleId, fn ($s) => $s->where('mahalle_id', $mahalleId));
                });
            })
            ->orderByDesc('id')
            ->paginate(20)->withQueryString();

        // Genel istatistik
        $sayilar = [
            'toplam' => Lojman::mudurlukKapsami()->count(),
            'bos' => Lojman::mudurlukKapsami()->where('durum', 'bos')->count(),
            'dolu' => Lojman::mudurlukKapsami()->where('durum', 'dolu')->count(),
            'bakim' => Lojman::mudurlukKapsami()->where('durum', 'bakimda')->count(),
        ];

        return view('panel.lojman.index', [
            'lojmanlar' => $lojmanlar,
            'iller' => Il::orderBy('ad')->get(['id', 'ad']),
            'ilceler' => $ilId ? Ilce::where('il_id', $ilId)->orderBy('ad')->get(['id', 'ad']) : collect(),
            'mahalleler' => $ilceId ? Mahalle::where('ilce_id', $ilceId)->orderBy('ad')->get(['id', 'ad']) : collect(),
            'filtre' => compact('q', 'ilId', 'ilceId', 'mahalleId', 'durum', 'tip'),
            'sayilar' => $sayilar,
        ]);
    }

    public function olustur(Request $request): View
    {
        return view('panel.lojman.olustur', [
            'iller' => Il::orderBy('ad')->get(['id', 'ad']),
        ]);
    }

    public function kaydet(Request $request): RedirectResponse
    {
        $veri = $this->lojmanValidate($request);
        $veri['mudurluk_id'] = auth()->user()->mudurluk_id ?? null;
        // Otomatik taşınmaz eşleme (mahalle+ada+parsel — BBN'de blok/daire_no ile daralt)
        $veri['tasinmaz_id'] = $this->tasinmazEsle($veri);

        $lojman = Lojman::create($veri);

        return redirect()->route('panel.lojman.detay', $lojman->id)->with('basari', 'Lojman kaydı oluşturuldu.');
    }

    public function detay(int $id): View
    {
        $lojman = Lojman::with([
            'tasinmaz.il:id,ad', 'tasinmaz.ilce:id,ad', 'tasinmaz.mahalle:id,ad',
            'mudurluk:id,ad',
            'basvurular', 'tahsisler.basvuru', 'evraklar',
        ])->findOrFail($id);
        $this->yetkiKontrolu($lojman);

        return view('panel.lojman.detay', ['lojman' => $lojman]);
    }

    public function duzenle(int $id): View
    {
        $lojman = Lojman::with(['tasinmaz.il:id,ad', 'tasinmaz.ilce:id,ad', 'tasinmaz.mahalle:id,ad'])->findOrFail($id);
        $this->yetkiKontrolu($lojman);

        return view('panel.lojman.duzenle', [
            'lojman' => $lojman,
            'iller' => Il::orderBy('ad')->get(['id', 'ad']),
            'ilceler' => $lojman->il_id ? Ilce::where('il_id', $lojman->il_id)->orderBy('ad')->get(['id', 'ad']) : collect(),
            'mahalleler' => $lojman->ilce_id ? Mahalle::where('ilce_id', $lojman->ilce_id)->orderBy('ad')->get(['id', 'ad']) : collect(),
        ]);
    }

    public function guncelle(Request $request, int $id): RedirectResponse
    {
        $lojman = Lojman::findOrFail($id);
        $this->yetkiKontrolu($lojman);

        $veri = $this->lojmanValidate($request);
        $veri['tasinmaz_id'] = $this->tasinmazEsle($veri);
        $lojman->update($veri);

        return redirect()->route('panel.lojman.detay', $lojman->id)->with('basari', 'Lojman güncellendi.');
    }

    /** Ortak validation kuralları. */
    private function lojmanValidate(Request $request): array
    {
        return $request->validate([
            'il_id' => 'required|integer|exists:iller,id',
            'ilce_id' => 'required|integer|exists:ilceler,id',
            'mahalle_id' => 'required|integer|exists:mahalleler,id',
            'ada' => 'required|string|max:20',
            'parsel' => 'required|string|max:20',
            'ad' => 'required|string|max:200',
            'blok' => 'nullable|string|max:50',
            'daire_no' => 'nullable|string|max:50',
            'kat' => 'nullable|integer|min:0|max:50',
            'oda_sayisi' => 'nullable|integer|min:0|max:20',
            'alan_m2' => 'nullable|numeric|min:0',
            'tip' => 'nullable|string|max:40',
            'durum' => 'required|in:bos,dolu,bakimda,kullanim-disi',
            'aciklama' => 'nullable|string|max:5000',
        ]);
    }

    /**
     * Lojman için taşınmaz eşleme.
     *
     * Kurallar:
     *  1) Sadece MESKEN tipi taşınmazlar (kayit_tipi = KatMulkiyetli veya
     *     KatMulkiyetsizBina). BosParsel eşleşmez — üzerinde bina yok.
     *  2) Eşleme mahalle+ada+parsel + BBN (blok/daire) kombine mantığı ile:
     *     - Blok/daire girildiyse mutlaka TasinmazYapi'da eşleşme aranır
     *     - Kat mülkiyetli çoklu tasinmaz varsa doğru daire seçilir
     *     - Kat mülkiyetsiz binada bir tasinmaz altında yapı olmalı
     *  3) Eşleşmezse null döner ama daha ayrıntılı sebep önizleme JSON'unda.
     *
     * @return array{tasinmaz_id: ?int, sebep: string, tasinmaz?: Tasinmaz|null, yapi?: mixed}
     */
    private function tasinmazEsleDetay(array $veri): array
    {
        if (empty($veri['mahalle_id']) || empty($veri['ada']) || empty($veri['parsel'])) {
            return ['tasinmaz_id' => null, 'sebep' => 'eksik-alan'];
        }

        // Adım 1: mahalle+ada+parsel eşleşen tüm tasinmazlar
        $adaylar = Tasinmaz::query()
            ->where('mahalle_id', $veri['mahalle_id'])
            ->where('ada', $veri['ada'])
            ->where('parsel', $veri['parsel'])
            ->with(['il:id,ad', 'ilce:id,ad', 'mahalle:id,ad'])
            ->get();

        if ($adaylar->isEmpty()) {
            return ['tasinmaz_id' => null, 'sebep' => 'parsel-yok'];
        }

        // Adım 2: sadece MESKEN taşınmazları (kayit_tipi ≠ bos_parsel)
        $meskenler = $adaylar->filter(fn ($t) => $t->kayit_tipi
            && $t->kayit_tipi !== \App\Enums\KayitTipi::BosParsel);

        if ($meskenler->isEmpty()) {
            return ['tasinmaz_id' => null, 'sebep' => 'sadece-arsa', 'tasinmaz' => $adaylar->first()];
        }

        // Adım 3: Blok/daire varsa TasinmazYapi ile kesin eşleştirme
        $blok = trim((string) ($veri['blok'] ?? ''));
        $daire = trim((string) ($veri['daire_no'] ?? ''));

        if ($blok !== '' || $daire !== '') {
            $yapiSorgu = \App\Models\TasinmazYapi::query()
                ->whereIn('tasinmaz_id', $meskenler->pluck('id'))
                ->when($blok !== '', fn ($q) => $q->where('blok_no', $blok))
                ->when($daire !== '', fn ($q) => $q->where('bagimsiz_bolum_no', $daire));

            $yapi = $yapiSorgu->first();
            if ($yapi) {
                $t = $meskenler->firstWhere('id', $yapi->tasinmaz_id);

                return ['tasinmaz_id' => (int) $yapi->tasinmaz_id, 'sebep' => 'bbn-esti', 'tasinmaz' => $t, 'yapi' => $yapi];
            }

            // BBN girildi ama TasinmazYapi'da bulunamadı
            return ['tasinmaz_id' => null, 'sebep' => 'bbn-yok', 'tasinmaz' => $meskenler->first()];
        }

        // Blok/daire girilmedi — tek mesken varsa direkt bağla
        if ($meskenler->count() === 1) {
            return ['tasinmaz_id' => (int) $meskenler->first()->id, 'sebep' => 'tek-mesken', 'tasinmaz' => $meskenler->first()];
        }

        // Çoklu mesken var ama BBN belirtilmedi — belirsiz, bağlama
        return ['tasinmaz_id' => null, 'sebep' => 'coklu-mesken-bbn-eksik', 'tasinmaz' => $meskenler->first()];
    }

    /** Kaydet/güncelle için — sadece id döner. */
    private function tasinmazEsle(array $veri): ?int
    {
        return $this->tasinmazEsleDetay($veri)['tasinmaz_id'] ?? null;
    }

    /**
     * AJAX endpoint — form doldurulurken canlı önizleme.
     * Kullanıcıya "neden eşleşmedi" konusunda ayrıntılı geri bildirim verir.
     */
    public function tasinmazOnizle(Request $request): JsonResponse
    {
        $veri = $request->validate([
            'mahalle_id' => 'required|integer|exists:mahalleler,id',
            'ada' => 'required|string|max:20',
            'parsel' => 'required|string|max:20',
            'daire_no' => 'nullable|string|max:50',
            'blok' => 'nullable|string|max:50',
        ]);
        $sonuc = $this->tasinmazEsleDetay($veri);

        $mesajlar = [
            'parsel-yok' => 'Bu mahalle/ada/parsel eşleşmesi sistemde yok. Lojman bağımsız kaydedilecek.',
            'sadece-arsa' => 'Bu parsel BOŞ PARSEL (arsa). Üzerinde bina/BBN yok — lojman bağlanamaz.',
            'bbn-yok' => 'Parsel mesken ama girilen blok/daire numarasıyla eşleşen bağımsız bölüm bulunamadı.',
            'coklu-mesken-bbn-eksik' => 'Bu parselde birden fazla mesken taşınmaz var. Doğru daireyi bulmak için blok ve/veya daire no girin.',
            'tek-mesken' => 'Mesken taşınmazla eşleşti.',
            'bbn-esti' => 'Bağımsız bölüm (BBN) tam olarak eşleşti.',
        ];

        $t = $sonuc['tasinmaz'] ?? null;
        $yapi = $sonuc['yapi'] ?? null;

        return response()->json([
            'bulundu' => (bool) ($sonuc['tasinmaz_id'] ?? null),
            'sebep' => $sonuc['sebep'],
            'mesaj' => $mesajlar[$sonuc['sebep']] ?? '',
            'tasinmaz' => $t ? [
                'id' => $t->id,
                'ada' => $t->ada,
                'parsel' => $t->parsel,
                'kayit_tipi' => $t->kayit_tipi?->etiket(),
                'il' => $t->il?->ad,
                'ilce' => $t->ilce?->ad,
                'mahalle' => $t->mahalle?->ad,
            ] : null,
            'yapi' => $yapi ? [
                'blok_no' => $yapi->blok_no,
                'kat_no' => $yapi->kat_no,
                'bagimsiz_bolum_no' => $yapi->bagimsiz_bolum_no,
                'nitelik' => $yapi->nitelik,
            ] : null,
        ]);
    }

    public function sil(int $id): RedirectResponse
    {
        $lojman = Lojman::findOrFail($id);
        $this->yetkiKontrolu($lojman);
        $lojman->delete();

        return redirect()->route('panel.lojman.index')->with('basari', 'Lojman silindi.');
    }

    // ============ BAŞVURU ============

    public function basvuruListe(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $durum = $request->query('durum');

        $basvurular = LojmanBasvuru::query()
            ->with(['lojman:id,ad'])
            ->when($q !== '', function ($w) use ($q) {
                $w->where('ad_soyad', 'like', "%{$q}%")
                    ->orWhere('tc_kimlik', 'like', "%{$q}%")
                    ->orWhere('sicil_no', 'like', "%{$q}%");
            })
            ->when($durum, fn ($w) => $w->where('durum', $durum))
            ->orderByDesc('basvuru_tarihi')
            ->paginate(20)->withQueryString();

        return view('panel.lojman.basvurular', [
            'basvurular' => $basvurular,
            'filtre' => compact('q', 'durum'),
        ]);
    }

    public function basvuruOlustur(Request $request): View
    {
        $lojmanId = $request->query('lojman_id');
        $lojman = $lojmanId ? Lojman::find($lojmanId) : null;

        return view('panel.lojman.basvuru-olustur', [
            'lojman' => $lojman,
            'lojmanlar' => Lojman::mudurlukKapsami()->orderBy('ad')->get(['id', 'ad']),
        ]);
    }

    public function basvuruKaydet(Request $request): RedirectResponse
    {
        $veri = $request->validate([
            'lojman_id' => 'nullable|integer|exists:lojmanlar,id',
            'ad_soyad' => 'required|string|max:200',
            'tc_kimlik' => 'nullable|string|size:11',
            'sicil_no' => 'nullable|string|max:50',
            'unvan' => 'nullable|string|max:150',
            'birim' => 'nullable|string|max:200',
            'tahsis_turu' => 'nullable|string|max:40',
            'basvuru_tarihi' => 'required|date',
            'durum' => 'required|in:basvuruldu,degerlendirmede,onaylandi,reddedildi,tahsisedildi',
            'aciklama' => 'nullable|string|max:5000',
        ]);
        $basvuru = LojmanBasvuru::create($veri);

        return redirect()->route('panel.lojman.basvuru-detay', $basvuru->id)->with('basari', 'Başvuru oluşturuldu.');
    }

    public function basvuruDetay(int $id): View
    {
        $basvuru = LojmanBasvuru::with(['lojman', 'tahsisRelation', 'evraklar'])->findOrFail($id);
        $lojmanlar = Lojman::mudurlukKapsami()->orderBy('ad')->get(['id', 'ad']);

        return view('panel.lojman.basvuru-detay', compact('basvuru', 'lojmanlar'));
    }

    public function basvuruGuncelle(Request $request, int $id): RedirectResponse
    {
        $basvuru = LojmanBasvuru::findOrFail($id);
        $veri = $request->validate([
            'lojman_id' => 'nullable|integer|exists:lojmanlar,id',
            'ad_soyad' => 'required|string|max:200',
            'tc_kimlik' => 'nullable|string|size:11',
            'sicil_no' => 'nullable|string|max:50',
            'unvan' => 'nullable|string|max:150',
            'birim' => 'nullable|string|max:200',
            'tahsis_turu' => 'nullable|string|max:40',
            'basvuru_tarihi' => 'required|date',
            'durum' => 'required|in:basvuruldu,degerlendirmede,onaylandi,reddedildi,tahsisedildi',
            'aciklama' => 'nullable|string|max:5000',
        ]);
        $basvuru->update($veri);

        return back()->with('basari', 'Başvuru güncellendi.');
    }

    public function basvuruSil(int $id): JsonResponse
    {
        $basvuru = LojmanBasvuru::findOrFail($id);
        $basvuru->delete();

        return response()->json(['ok' => true]);
    }

    // ============ TAHSIS ============

    public function tahsisKaydet(Request $request, int $lojmanId): RedirectResponse
    {
        $lojman = Lojman::findOrFail($lojmanId);
        $this->yetkiKontrolu($lojman);

        $veri = $request->validate([
            'basvuru_id' => 'nullable|integer|exists:lojman_basvurulari,id',
            'ad_soyad' => 'required|string|max:200',
            'sicil_no' => 'nullable|string|max:50',
            'baslangic_tarihi' => 'required|date',
            'bitis_tarihi' => 'nullable|date|after_or_equal:baslangic_tarihi',
            'karar_no' => 'nullable|string|max:100',
            'karar_tarihi' => 'nullable|date',
            'aciklama' => 'nullable|string|max:2000',
        ]);

        DB::transaction(function () use ($lojman, $veri) {
            LojmanTahsis::create(array_merge($veri, ['lojman_id' => $lojman->id]));
            // Lojman durumunu 'dolu' yap (aktif tahsis oluştuysa)
            if (! $veri['bitis_tarihi'] || $veri['bitis_tarihi'] >= now()->toDateString()) {
                $lojman->update(['durum' => 'dolu']);
            }
            // Başvuruyu tahsisedildi yap
            if (! empty($veri['basvuru_id'])) {
                LojmanBasvuru::whereKey($veri['basvuru_id'])->update(['durum' => 'tahsisedildi']);
            }
        });

        return back()->with('basari', 'Tahsis kaydedildi.');
    }

    public function tahsisSonlandir(int $tahsisId): RedirectResponse
    {
        $t = LojmanTahsis::findOrFail($tahsisId);
        $this->yetkiKontrolu($t->lojman);
        $t->update(['bitis_tarihi' => now()->toDateString()]);
        // Lojman durumu boş
        $t->lojman->update(['durum' => 'bos']);

        return back()->with('basari', 'Tahsis sonlandırıldı.');
    }

    public function tahsisSil(int $tahsisId): JsonResponse
    {
        $t = LojmanTahsis::findOrFail($tahsisId);
        $this->yetkiKontrolu($t->lojman);
        $t->delete();

        return response()->json(['ok' => true]);
    }

    // ============ EVRAK ============

    public function evrakYukle(Request $request): RedirectResponse
    {
        $veri = $request->validate([
            'basvuru_id' => 'nullable|integer|exists:lojman_basvurulari,id',
            'lojman_id' => 'nullable|integer|exists:lojmanlar,id',
            'kategori' => 'required|in:basvuru,gorus-gelen,gorus-giden,encumen,tahsis,diger',
            'evrak_no' => 'nullable|string|max:100',
            'evrak_tarihi' => 'nullable|date',
            'dosya' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:20480',
            'aciklama' => 'nullable|string|max:2000',
        ]);
        if ($request->hasFile('dosya')) {
            $veri['dosya_yolu'] = $request->file('dosya')->store('lojman/evraklar/'.now()->format('Y/m'), 'public');
        }
        unset($veri['dosya']);

        LojmanEvrak::create($veri);

        return back()->with('basari', 'Evrak eklendi.');
    }

    public function evrakSil(int $id): JsonResponse
    {
        $e = LojmanEvrak::findOrFail($id);
        if ($e->dosya_yolu) {
            Storage::disk('public')->delete($e->dosya_yolu);
        }
        $e->delete();

        return response()->json(['ok' => true]);
    }

    // ============ EXCEL EXPORT/IMPORT ============

    /**
     * Lojman listesini Excel olarak indir.
     * Seçili id'ler varsa (?ids=1,2,3) sadece onları — yoksa tüm filtreli sonuç.
     */
    public function excelIndir(Request $request)
    {
        $ids = $request->filled('ids') ? array_filter(explode(',', $request->query('ids'))) : null;
        $lojmanlar = Lojman::query()
            ->mudurlukKapsami()
            ->with(['il:id,ad', 'ilce:id,ad', 'mahalle:id,ad', 'tasinmaz:id,kayit_tipi'])
            ->withCount(['basvurular', 'tahsisler'])
            ->when($ids, fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('id')
            ->get();

        $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sh = $ss->getActiveSheet();
        $sh->setTitle('Lojmanlar');

        $basliklar = ['ID', 'Ad', 'İl', 'İlçe', 'Mahalle', 'Ada', 'Parsel', 'Blok', 'Daire No', 'Kat', 'Oda Sayısı', 'Alan (m²)', 'Tip', 'Durum', 'Başvuru', 'Tahsis', 'Bağlı Taşınmaz ID', 'Açıklama'];
        $sh->fromArray($basliklar, null, 'A1');
        $sh->getStyle('A1:R1')->getFont()->setBold(true);
        $sh->getStyle('A1:R1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('16223C');
        $sh->getStyle('A1:R1')->getFont()->getColor()->setRGB('FFFFFF');
        $sh->getRowDimension(1)->setRowHeight(24);

        $durumEt = ['bos' => 'Boş', 'dolu' => 'Dolu', 'bakimda' => 'Bakımda', 'kullanim-disi' => 'Kullanım Dışı'];
        $row = 2;
        foreach ($lojmanlar as $l) {
            $sh->fromArray([
                $l->id, $l->ad, $l->il?->ad, $l->ilce?->ad, $l->mahalle?->ad, $l->ada, $l->parsel,
                $l->blok, $l->daire_no, $l->kat, $l->oda_sayisi, $l->alan_m2 !== null ? (float) $l->alan_m2 : null,
                $l->tip, $durumEt[$l->durum] ?? $l->durum, $l->basvurular_count, $l->tahsisler_count,
                $l->tasinmaz_id ?? '', $l->aciklama,
            ], null, 'A'.$row);
            $row++;
        }
        foreach (range('A', 'R') as $c) {
            $sh->getColumnDimension($c)->setAutoSize(true);
        }
        $sh->freezePane('A2');

        $temp = tempnam(sys_get_temp_dir(), 'lojman');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save($temp);

        return response()->download($temp, 'lojmanlar-'.now()->format('Y-m-d-Hi').'.xlsx')->deleteFileAfterSend(true);
    }

    /**
     * Başvuru listesini Excel olarak indir.
     */
    public function basvuruExcelIndir(Request $request)
    {
        $ids = $request->filled('ids') ? array_filter(explode(',', $request->query('ids'))) : null;
        $basvurular = LojmanBasvuru::query()
            ->with(['lojman:id,ad'])
            ->when($ids, fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('id')
            ->get();

        $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sh = $ss->getActiveSheet();
        $sh->setTitle('Basvurular');
        $basliklar = ['ID', 'Ad Soyad', 'TC Kimlik', 'Sicil No', 'Ünvan', 'Birim', 'Tahsis Türü', 'Başvuru Tarihi', 'Lojman', 'Durum', 'Açıklama'];
        $sh->fromArray($basliklar, null, 'A1');
        $sh->getStyle('A1:K1')->getFont()->setBold(true);
        $sh->getStyle('A1:K1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('16223C');
        $sh->getStyle('A1:K1')->getFont()->getColor()->setRGB('FFFFFF');

        $et = ['basvuruldu' => 'Başvuruldu', 'degerlendirmede' => 'Değerlendirmede', 'onaylandi' => 'Onaylandı', 'reddedildi' => 'Reddedildi', 'tahsisedildi' => 'Tahsis Edildi'];
        $row = 2;
        foreach ($basvurular as $b) {
            $sh->fromArray([
                $b->id, $b->ad_soyad, $b->tc_kimlik, $b->sicil_no, $b->unvan, $b->birim,
                $b->tahsis_turu, optional($b->basvuru_tarihi)->format('d.m.Y'),
                $b->lojman?->ad ?? '', $et[$b->durum] ?? $b->durum, $b->aciklama,
            ], null, 'A'.$row);
            $row++;
        }
        foreach (range('A', 'K') as $c) {
            $sh->getColumnDimension($c)->setAutoSize(true);
        }
        $sh->freezePane('A2');
        $temp = tempnam(sys_get_temp_dir(), 'lojman-basvuru');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save($temp);

        return response()->download($temp, 'lojman-basvurulari-'.now()->format('Y-m-d-Hi').'.xlsx')->deleteFileAfterSend(true);
    }

    /**
     * Tahsis listesini Excel olarak indir.
     */
    public function tahsisExcelIndir(Request $request)
    {
        $tahsisler = LojmanTahsis::query()
            ->with(['lojman:id,ad,blok,daire_no'])
            ->orderByDesc('baslangic_tarihi')
            ->get();

        $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sh = $ss->getActiveSheet();
        $sh->setTitle('Tahsisler');
        $basliklar = ['ID', 'Lojman', 'Blok/Daire', 'Ad Soyad', 'Sicil', 'Başlangıç', 'Bitiş', 'Gün', 'Durum', 'Karar No', 'Karar Tarihi', 'Açıklama'];
        $sh->fromArray($basliklar, null, 'A1');
        $sh->getStyle('A1:L1')->getFont()->setBold(true);
        $sh->getStyle('A1:L1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('16223C');
        $sh->getStyle('A1:L1')->getFont()->getColor()->setRGB('FFFFFF');

        $row = 2;
        foreach ($tahsisler as $t) {
            $aktif = ! $t->bitis_tarihi || $t->bitis_tarihi >= now();
            $sh->fromArray([
                $t->id, $t->lojman?->ad ?? '',
                trim(($t->lojman?->blok ?? '').'/'.($t->lojman?->daire_no ?? ''), '/'),
                $t->ad_soyad, $t->sicil_no,
                optional($t->baslangic_tarihi)->format('d.m.Y'),
                optional($t->bitis_tarihi)->format('d.m.Y') ?? 'Devam',
                $t->gunSayisi(), $aktif ? 'Aktif' : 'Sonlandı',
                $t->karar_no, optional($t->karar_tarihi)->format('d.m.Y'), $t->aciklama,
            ], null, 'A'.$row);
            $row++;
        }
        foreach (range('A', 'L') as $c) {
            $sh->getColumnDimension($c)->setAutoSize(true);
        }
        $sh->freezePane('A2');
        $temp = tempnam(sys_get_temp_dir(), 'lojman-tahsis');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save($temp);

        return response()->download($temp, 'lojman-tahsisleri-'.now()->format('Y-m-d-Hi').'.xlsx')->deleteFileAfterSend(true);
    }

    /**
     * Toplu Excel'den lojman yükleme için şablon indir.
     */
    public function excelSablonIndir()
    {
        $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sh = $ss->getActiveSheet();
        $sh->setTitle('Lojman Şablonu');
        $basliklar = ['Lojman Adı*', 'İl Adı*', 'İlçe Adı*', 'Mahalle Adı*', 'Ada*', 'Parsel*', 'Blok', 'Daire No', 'Kat', 'Oda Sayısı', 'Alan m²', 'Tip', 'Durum (bos/dolu/bakimda)', 'Açıklama'];
        $sh->fromArray($basliklar, null, 'A1');
        $sh->getStyle('A1:N1')->getFont()->setBold(true);
        $sh->getStyle('A1:N1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F4E5B7');
        $sh->fromArray(['Merkez Lojman A - Daire 3', 'Ankara', 'Mamak', 'Kusunlar', '12345', '5', 'A', '3', 2, 3, 95, 'Memur', 'bos', 'Örnek satır — silin'], null, 'A2');
        $sh->getStyle('A2:N2')->getFont()->setItalic(true)->getColor()->setRGB('999999');
        foreach (range('A', 'N') as $c) {
            $sh->getColumnDimension($c)->setAutoSize(true);
        }
        $temp = tempnam(sys_get_temp_dir(), 'lojman-sablon');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save($temp);

        return response()->download($temp, 'lojman-toplu-yukleme-sablonu.xlsx')->deleteFileAfterSend(true);
    }

    /**
     * Excel'den toplu lojman yükle.
     * Beklenen sütunlar (1. satır başlık): Ad | İl | İlçe | Mahalle | Ada | Parsel | Blok | Daire No | Kat | Oda | Alan | Tip | Durum | Açıklama
     * İl/İlçe/Mahalle adları veritabanıyla eşleştirilir; taşınmaz otomatik bağlanır.
     */
    public function excelYukle(Request $request): RedirectResponse
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($request->file('excel_file')->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        array_shift($rows); // başlık atla

        $eklenen = 0;
        $hatali = 0;
        $mesajlar = [];

        DB::transaction(function () use ($rows, &$eklenen, &$hatali, &$mesajlar) {
            foreach ($rows as $idx => $r) {
                $ad = trim((string) ($r['A'] ?? ''));
                $ilAd = trim((string) ($r['B'] ?? ''));
                $ilceAd = trim((string) ($r['C'] ?? ''));
                $mahalleAd = trim((string) ($r['D'] ?? ''));
                $ada = trim((string) ($r['E'] ?? ''));
                $parsel = trim((string) ($r['F'] ?? ''));
                if ($ad === '' && $ada === '' && $parsel === '') {
                    continue;
                }
                if ($ad === '' || $ilAd === '' || $ilceAd === '' || $mahalleAd === '' || $ada === '' || $parsel === '') {
                    $hatali++;
                    $mesajlar[] = "Satır ".($idx + 1).": zorunlu alan boş.";

                    continue;
                }

                $il = Il::where('ad', $ilAd)->first();
                if (! $il) {
                    $hatali++;
                    $mesajlar[] = "Satır ".($idx + 1).": İl '{$ilAd}' bulunamadı.";

                    continue;
                }
                $ilce = Ilce::where('il_id', $il->id)->where('ad', $ilceAd)->first();
                if (! $ilce) {
                    $hatali++;
                    $mesajlar[] = "Satır ".($idx + 1).": İlçe '{$ilceAd}' bulunamadı.";

                    continue;
                }
                $mahalle = Mahalle::where('ilce_id', $ilce->id)->where('ad', $mahalleAd)->first();
                if (! $mahalle) {
                    $hatali++;
                    $mesajlar[] = "Satır ".($idx + 1).": Mahalle '{$mahalleAd}' bulunamadı.";

                    continue;
                }

                $durum = strtolower(trim((string) ($r['M'] ?? 'bos')));
                if (! in_array($durum, ['bos', 'dolu', 'bakimda', 'kullanim-disi'], true)) {
                    $durum = 'bos';
                }

                $veri = [
                    'ad' => $ad,
                    'il_id' => $il->id,
                    'ilce_id' => $ilce->id,
                    'mahalle_id' => $mahalle->id,
                    'ada' => $ada,
                    'parsel' => $parsel,
                    'blok' => trim((string) ($r['G'] ?? '')) ?: null,
                    'daire_no' => trim((string) ($r['H'] ?? '')) ?: null,
                    'kat' => is_numeric($r['I'] ?? null) ? (int) $r['I'] : null,
                    'oda_sayisi' => is_numeric($r['J'] ?? null) ? (int) $r['J'] : null,
                    'alan_m2' => is_numeric($r['K'] ?? null) ? (float) str_replace(',', '.', (string) $r['K']) : null,
                    'tip' => trim((string) ($r['L'] ?? '')) ?: null,
                    'durum' => $durum,
                    'aciklama' => trim((string) ($r['N'] ?? '')) ?: null,
                    'mudurluk_id' => auth()->user()->mudurluk_id ?? null,
                ];
                $veri['tasinmaz_id'] = $this->tasinmazEsle($veri);
                Lojman::create($veri);
                $eklenen++;
            }
        });

        $mesaj = "Toplu yükleme tamamlandı: {$eklenen} lojman eklendi";
        if ($hatali > 0) {
            $mesaj .= ", {$hatali} satır atlandı (".implode(' · ', array_slice($mesajlar, 0, 5)).(count($mesajlar) > 5 ? ' ...' : '').')';
        }

        return redirect()->route('panel.lojman.index')->with('basari', $mesaj);
    }

    // ============ Yardımcı ============

    private function yetkiKontrolu(Lojman $lojman): void
    {
        $u = auth()->user();
        if (! $u instanceof Kullanici) {
            abort(403);
        }
        if ($u->izinVarMi('tasinmaz.tumunu-gor')) {
            return;
        }
        abort_unless(! $lojman->mudurluk_id || $lojman->mudurluk_id === $u->mudurluk_id, 403);
    }
}
