<?php

namespace App\Http\Controllers;

use App\Models\Ilce;
use App\Models\KiraAlan;
use App\Models\KiraDosyasi;
use App\Models\KiraHukukiDurum;
use App\Models\KiraIsKolu;
use App\Models\KiraKiracilikDurum;
use App\Models\KiraPoligon;
use App\Models\KiraTasinmaz;
use App\Models\Mahalle;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Kira alanları — bir dosyada kiraya verilen tek tek yerler.
 *
 * Referans sistemde bu ekran dosya listesinden kalabalık (5.693 alan /
 * 4.353 dosya): bir parkın içindeki büfe, kort, otopark ayrı alanlardır ve
 * her birinin kendi kiracısı, süresi, bedeli ve geometrisi vardır.
 */
class KiraAlanController extends Controller
{
    /** Liste: doluluk sekmeleri, süzgeçler, özet; ?excel=1 aynı süzgeçle indirir. */
    public function index(Request $request): View|StreamedResponse
    {
        $f = $this->filtre($request);
        $taban = $this->sorgu($f);

        $sorgu = (clone $taban)
            ->with([
                'dosya:id,dosya_no,isyeri_adi,ihale_turu',
                'dosya.anaKiraci',
                'dosya.tasinmaz:id,kira_dosya_id,ilce_id,mahalle_id,ada,parsel',
                'dosya.tasinmaz.ilce:id,ad',
                'dosya.tasinmaz.mahalle:id,ad',
                'isKolu:id,ad',
                'poligon:id,ad,merkez_lat,merkez_lng',
                'kiracilikDurum:id,ad',
                'hukukiDurum:id,ad',
            ])
            ->orderByDesc('id');

        if ($request->boolean('excel')) {
            return $this->excelYaz($sorgu->get());
        }

        $durumsuz = $this->sorgu(['dolu_bos' => null] + $f);

        return view('panel.kira.alanlar.index', [
            'kayitlar' => $sorgu->paginate(50)->withQueryString(),
            'sayac' => [
                'tumu' => (clone $durumsuz)->count(),
                'dolu' => (clone $durumsuz)->where('dolu_bos', 'dolu')->count(),
                'bos' => (clone $durumsuz)->where('dolu_bos', 'bos')->count(),
            ],
            'ozet' => [
                'toplam_alan' => (float) (clone $taban)->sum('alan'),
                'yillik_toplam' => (float) (clone $taban)->sum('yillik_bedel'),
                'bir_ay' => (clone $taban)->whereBetween('bitis_tarihi', [now()->toDateString(), now()->addDays(30)->toDateString()])->count(),
                'dolmus' => (clone $taban)->whereNotNull('bitis_tarihi')->where('bitis_tarihi', '<', now()->toDateString())->count(),
            ],
            'filtre' => $f,
            'isKollari' => KiraIsKolu::aktif()->get(['id', 'ad']),
            'hukukiDurumlar' => KiraHukukiDurum::aktif()->get(['id', 'ad']),
            'kiracilikDurumlar' => KiraKiracilikDurum::aktif()->get(['id', 'ad']),
            'ilceler' => Ilce::orderBy('ad')->get(['id', 'ad']),
            'mahalleler' => $f['ilce_id'] ? Mahalle::where('ilce_id', $f['ilce_id'])->orderBy('ad')->get(['id', 'ad']) : collect(),
        ]);
    }

    public function excelIndir(Request $request): StreamedResponse
    {
        return $this->index($request->merge(['excel' => 1]));
    }

    /** Bilgi kartı: alanın tüm bilgileri, poligonu ve belgeleri. */
    public function detay(int $id): View
    {
        $alan = KiraAlan::with([
            'dosya.kiracilar', 'dosya.isKolu', 'dosya.tasinmaz.ilce', 'dosya.tasinmaz.mahalle',
            // Alanın kendi dönemi/bedeli yoksa dosyanın sözleşmesi geçerli sayılır
            'dosya.aktifSozlesme.bedeller',
            'isKolu', 'kiracilikDurum', 'hukukiDurum',
            'poligon.alanlar.dosya:id,dosya_no,isyeri_adi',
            'belgeler.yukleyen',
        ])->findOrFail($id);

        return view('panel.kira.alanlar.detay', [
            'alan' => $alan,
            // Aynı poligonu paylaşan diğer kayıtlar — kartta listelenir
            'komsuKayitlar' => $alan->poligon?->alanlar->where('id', '!=', $alan->id) ?? collect(),
            // Künyede "bu dosyada N ayrı yer kiraya verilmiş" için
            'dosyaAlanSayisi' => KiraAlan::where('kira_dosya_id', $alan->kira_dosya_id)->count(),
        ]);
    }

    /**
     * Form. Haritadan gelirken ?poligon=<id> taşınır: çizilen ya da seçilen
     * sınır hazır işaretli açılır.
     */
    public function olustur(Request $request): View
    {
        $dosya = $request->query('dosya')
            ? KiraDosyasi::with('tasinmaz')->findOrFail($request->query('dosya'))
            : null;

        $poligonId = $request->query('poligon') ? (int) $request->query('poligon') : null;

        return view('panel.kira.alanlar.form', $this->formVerisi(null, $dosya, $poligonId));
    }

    public function kaydet(Request $request): RedirectResponse
    {
        $veri = $this->dogrula($request);
        $alan = KiraAlan::create($veri + ['aktif_mi' => $request->boolean('aktif_mi', true)]);

        return redirect()->route('panel.kira.alanlar.detay', $alan->id)
            ->with('basari', 'Kira alanı eklendi.');
    }

    public function duzenle(int $id): View
    {
        $alan = KiraAlan::with('dosya.tasinmaz')->findOrFail($id);

        return view('panel.kira.alanlar.form', $this->formVerisi($alan, $alan->dosya, $alan->poligon_id));
    }

    public function guncelle(Request $request, int $id): RedirectResponse
    {
        $alan = KiraAlan::findOrFail($id);
        $alan->update($this->dogrula($request) + ['aktif_mi' => $request->boolean('aktif_mi')]);

        return redirect()->route('panel.kira.alanlar.detay', $alan->id)
            ->with('basari', 'Kira alanı güncellendi.');
    }

    public function sil(int $id): RedirectResponse
    {
        $alan = KiraAlan::findOrFail($id);
        $dosyaId = $alan->kira_dosya_id;
        $alan->delete();

        return redirect()->route('panel.kira.detay', $dosyaId)
            ->with('basari', 'Kira alanı silindi.');
    }

    /** @return array<string, mixed> */
    private function filtre(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'dosya_no' => trim((string) $request->query('dosya_no', '')),
            'kiraci' => trim((string) $request->query('kiraci', '')),
            'dolu_bos' => in_array($request->query('dolu_bos'), ['dolu', 'bos'], true) ? $request->query('dolu_bos') : null,
            'is_kolu_id' => $request->query('is_kolu_id') ?: null,
            'hukuki_durum_id' => $request->query('hukuki_durum_id') ?: null,
            'kiracilik_durum_id' => $request->query('kiracilik_durum_id') ?: null,
            'ilce_id' => $request->query('ilce_id') ?: null,
            'mahalle_id' => $request->query('mahalle_id') ?: null,
            'ada' => trim((string) $request->query('ada', '')),
            'parsel' => trim((string) $request->query('parsel', '')),
            'baslangic' => $request->date('baslangic')?->format('Y-m-d'),
            'bitis' => $request->date('bitis')?->format('Y-m-d'),
            'kalan' => in_array((int) $request->query('kalan'), [30, 90, 180], true) ? (int) $request->query('kalan') : null,
            'durum' => in_array($request->query('durum'), ['dolmus', 'aktif', 'pasif'], true) ? $request->query('durum') : null,
            'poligon_id' => $request->query('poligon_id') ?: null,
        ];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return Builder<KiraAlan>
     */
    private function sorgu(array $f): Builder
    {
        return KiraAlan::query()
            ->when($f['q'] !== '', fn ($s) => $s->where(fn ($w) => $w
                ->where('ad', 'like', "%{$f['q']}%")
                ->orWhere('kiraci_ad_unvan', 'like', "%{$f['q']}%")
                ->orWhere('faaliyet_konusu', 'like', "%{$f['q']}%")
                ->orWhere('adres', 'like', "%{$f['q']}%")
                ->orWhereIn('kira_dosya_id', KiraDosyasi::query()->select('id')->ara($f['q']))))
            ->when($f['dosya_no'] !== '', fn ($s) => $s->whereIn('kira_dosya_id', KiraDosyasi::query()->select('id')
                ->where(fn ($w) => $w->where('dosya_no', 'like', "%{$f['dosya_no']}%")->orWhere('eski_dosya_no', 'like', "%{$f['dosya_no']}%"))))
            ->when($f['kiraci'] !== '', fn ($s) => $s->where('kiraci_ad_unvan', 'like', "%{$f['kiraci']}%"))
            ->when($f['dolu_bos'], fn ($s, $v) => $s->where('dolu_bos', $v))
            ->when($f['is_kolu_id'], fn ($s, $v) => $s->where('is_kolu_id', $v))
            ->when($f['hukuki_durum_id'], fn ($s, $v) => $s->where('hukuki_durum_id', $v))
            ->when($f['kiracilik_durum_id'], fn ($s, $v) => $s->where('kiracilik_durum_id', $v))
            ->when($f['poligon_id'], fn ($s, $v) => $s->where('poligon_id', $v))
            ->when($f['durum'] === 'aktif', fn ($s) => $s->where('aktif_mi', true))
            ->when($f['durum'] === 'pasif', fn ($s) => $s->where('aktif_mi', false))
            ->when($f['durum'] === 'dolmus', fn ($s) => $s->whereNotNull('bitis_tarihi')->where('bitis_tarihi', '<', now()->toDateString()))
            ->when($f['kalan'], fn ($s, $v) => $s->whereBetween('bitis_tarihi', [now()->toDateString(), now()->addDays($v)->toDateString()]))
            ->when($f['baslangic'], fn ($s, $v) => $s->where('baslangic_tarihi', '>=', $v))
            ->when($f['bitis'], fn ($s, $v) => $s->where('bitis_tarihi', '<=', $v))
            // Konum süzgeçleri dosyanın taşınmazı üzerinden; taşınmaz bir kez süzülür
            ->when($f['ilce_id'] || $f['mahalle_id'] || $f['ada'] !== '' || $f['parsel'] !== '',
                fn ($s) => $s->whereIn('kira_dosya_id', KiraTasinmaz::query()->select('kira_dosya_id')
                    ->when($f['ilce_id'], fn ($t, $v) => $t->where('ilce_id', $v))
                    ->when($f['mahalle_id'], fn ($t, $v) => $t->where('mahalle_id', $v))
                    ->when($f['ada'] !== '', fn ($t) => $t->where('ada', $f['ada']))
                    ->when($f['parsel'] !== '', fn ($t) => $t->where('parsel', $f['parsel']))));
    }

    /** @return array<string, mixed> */
    private function formVerisi(?KiraAlan $alan, ?KiraDosyasi $dosya, ?int $poligonId = null): array
    {
        return [
            'alan' => $alan,
            'dosya' => $dosya,
            // Haritadan taşınan sınır: formda hazır seçili gelir
            'seciliPoligonId' => $poligonId,
            'dosyalar' => KiraDosyasi::orderBy('dosya_no')->limit(1000)->get(['id', 'dosya_no', 'isyeri_adi']),
            'isKollari' => KiraIsKolu::aktif()->get(['id', 'ad']),
            'hukukiDurumlar' => KiraHukukiDurum::aktif()->get(['id', 'ad']),
            'kiracilikDurumlar' => KiraKiracilikDurum::aktif()->get(['id', 'ad']),
            // Aynı parselde çizilmiş poligonlar — yeniden çizmek yerine seçilir
            'poligonlar' => $this->adayPoligonlar($alan, $dosya, $poligonId),
        ];
    }

    /**
     * Form için poligon adayları: önce alanın kendi poligonu, sonra dosyanın
     * parselinde daha önce çizilmiş poligonlar.
     *
     * @return Collection<int, KiraPoligon>
     */
    private function adayPoligonlar(?KiraAlan $alan, ?KiraDosyasi $dosya, ?int $poligonId = null): Collection
    {
        $t = $dosya?->tasinmaz;

        return KiraPoligon::query()
            ->withCount('alanlar')
            ->where(function ($w) use ($alan, $t, $poligonId) {
                $w->when($alan?->poligon_id, fn ($x, $v) => $x->orWhere('id', $v));
                $w->when($poligonId, fn ($x, $v) => $x->orWhere('id', $v));
                if ($t?->mahalle_id && $t->ada && $t->parsel) {
                    $w->orWhere(fn ($x) => $x->where('mahalle_id', $t->mahalle_id)->where('ada', $t->ada)->where('parsel', $t->parsel));
                }
            })
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }

    /** @return array<string, mixed> */
    private function dogrula(Request $request): array
    {
        return $request->validate([
            'kira_dosya_id' => ['required', 'exists:kira_dosyalari,id'],
            'poligon_id' => ['nullable', 'exists:kira_poligonlari,id'],
            'ad' => ['nullable', 'string', 'max:200'],
            'adres' => ['nullable', 'string', 'max:1000'],
            'alan' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'faaliyet_konusu' => ['nullable', 'string', 'max:200'],
            'kiralama_amaci' => ['nullable', 'string', 'max:200'],
            'is_kolu_id' => ['nullable', 'exists:kira_is_kollari,id'],
            'yillik_bedel' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'taksit_sayisi' => ['nullable', 'integer', 'min:1', 'max:255'],
            'baslangic_tarihi' => ['nullable', 'date'],
            'bitis_tarihi' => ['nullable', 'date', 'after:baslangic_tarihi'],
            'dolu_bos' => ['required', Rule::in(['dolu', 'bos'])],
            'kiraci_ad_unvan' => ['nullable', 'string', 'max:250'],
            'alt_kiraci' => ['nullable', 'string', 'max:250'],
            'kiracilik_durum_id' => ['nullable', 'exists:kira_kiracilik_durumlari,id'],
            'hukuki_durum_id' => ['nullable', 'exists:kira_hukuki_durumlar,id'],
            'tahliye_ihbarnamesi_mi' => ['nullable', 'boolean'],
            'aciklama' => ['nullable', 'string', 'max:5000'],
        ], [
            'bitis_tarihi.after' => 'Bitiş tarihi başlangıçtan sonra olmalı.',
        ]);
    }

    /** @param  Collection<int, KiraAlan>  $kayitlar */
    private function excelYaz(Collection $kayitlar): StreamedResponse
    {
        $ss = new Spreadsheet;
        $sh = $ss->getActiveSheet();
        $sh->setTitle('Kira Alanları');
        $basliklar = ['Dosya No', 'Alan Adı', 'Kiracı', 'İş Kolu', 'Faaliyet', 'İlçe', 'Mahalle', 'Ada/Parsel', 'Alan (m²)', 'Yıllık Bedel (₺)', 'Başlangıç', 'Bitiş', 'Doluluk', 'Hukuki Durum'];
        $sh->fromArray($basliklar, null, 'A1');
        $son = 'N';
        $sh->getStyle("A1:{$son}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0D9488']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);

        $r = 2;
        foreach ($kayitlar as $a) {
            $t = $a->dosya?->tasinmaz;
            $sh->fromArray([
                $a->dosya?->dosya_no, $a->ad, $a->kiraci(), $a->isKolu?->ad, $a->faaliyet_konusu,
                $t?->ilce?->ad, $t?->mahalle?->ad, $t?->adaParsel(),
                $a->alan !== null ? (float) $a->alan : null,
                $a->yillik_bedel !== null ? (float) $a->yillik_bedel : null,
                $a->baslangic_tarihi?->format('d.m.Y'), $a->bitis_tarihi?->format('d.m.Y'),
                $a->dolu_bos->etiket(), $a->hukukiDurum?->ad,
            ], null, 'A'.$r);
            $r++;
        }
        $sh->getStyle('I2:J'.max(2, $r - 1))->getNumberFormat()->setFormatCode('#,##0.00');
        foreach (range('A', $son) as $c) {
            $sh->getColumnDimension($c)->setAutoSize(true);
        }
        $sh->freezePane('A2');
        $sh->setAutoFilter("A1:{$son}".max(1, $r - 1));

        return response()->streamDownload(fn () => (new Xlsx($ss))->save('php://output'), 'kira-alanlari-'.now()->format('Y-m-d').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
