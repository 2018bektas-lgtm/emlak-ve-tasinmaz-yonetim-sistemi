<?php

namespace App\Http\Controllers;

use App\Enums\KayitTipi;
use App\Models\EmsalParsel;
use App\Models\Il;
use App\Models\Ilce;
use App\Models\ImarDurumu;
use App\Models\Kullanici;
use App\Models\Mahalle;
use App\Models\Tasinmaz;
use App\Models\TasinmazDegerleme;
use App\Models\TasinmazImar;
use App\Models\TasinmazKoordinat;
use App\Support\HaritaGorunum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Taşınmaz Değerleme (değerleme müdürlüğü projesinden uyarlandı).
 *
 * Kaynak projede değerleme kaydı haritadan taşınmaz da oluşturuyordu; burada
 * değerleme yalnızca envanterdeki bir taşınmazdan açılır. Arsa / bağımsız
 * bölüm ayrımı taşınmazın kayıt tipinden gelir (TasinmazDegerleme::tipler),
 * BB bilgileri taşınmazın yapı satırından doldurulur. Word raporu yerine
 * yazdırılabilir rapor sayfası (tarayıcıdan PDF) üretilir.
 */
class DegerlemeController extends Controller
{
    private const EVRAK_KLASOR = 'degerleme/raporlar';

    /** Liste: tür sekmesi, dönem, arama; ?excel=1 aynı süzgeçle indirir. */
    public function index(Request $request): View|StreamedResponse
    {
        $tip = in_array($request->query('tip'), ['arsa', 'bb'], true) ? $request->query('tip') : null;
        $donem = in_array($request->query('donem'), ['bugun', 'hafta', 'ay'], true) ? $request->query('donem') : null;
        $q = trim((string) $request->query('q', ''));

        $taban = TasinmazDegerleme::query()->ara($q)->when($donem, fn ($s) => $this->donemSuz($s, $donem));
        $sorgu = (clone $taban)
            ->with(['tasinmaz:id,il_id,ilce_id,mahalle_id,ada,parsel,alan,kayit_tipi', 'tasinmaz.ilce:id,ad', 'tasinmaz.mahalle:id,ad,tkgm_id', 'hazirlayan:id,ad,soyad'])
            ->when($tip, fn ($s) => $s->where('tip', $tip))
            ->orderByDesc('rapor_tarihi')->orderByDesc('id');

        if ($request->boolean('excel')) {
            return $this->excelYaz($sorgu->get());
        }

        $sayac = [
            'tumu' => (clone $taban)->count(),
            'arsa' => (clone $taban)->where('tip', 'arsa')->count(),
            'bb' => (clone $taban)->where('tip', 'bb')->count(),
        ];
        $ozet = [
            'toplam_bedel' => (float) (clone $taban)->when($tip, fn ($s) => $s->where('tip', $tip))->sum('toplam_bedel'),
            'bu_ay' => TasinmazDegerleme::where('created_at', '>=', now()->startOfMonth())->count(),
            'evraksiz' => TasinmazDegerleme::whereNull('evrak')->count(),
        ];

        return view('panel.degerleme.index', [
            'kayitlar' => $sorgu->paginate(20)->withQueryString(),
            'sayac' => $sayac,
            'ozet' => $ozet,
            'filtre' => compact('tip', 'donem', 'q'),
        ]);
    }

    public function excelIndir(Request $request): StreamedResponse
    {
        return $this->index($request->merge(['excel' => 1]));
    }

    /** Yeni değerleme: taşınmaz arama (il/ilçe/mahalle/ada/parsel). */
    public function yeni(Request $request): View
    {
        $f = [
            'il_id' => $request->query('il_id'),
            'ilce_id' => $request->query('ilce_id'),
            'mahalle_id' => $request->query('mahalle_id'),
            'ada' => trim((string) $request->query('ada', '')),
            'parsel' => trim((string) $request->query('parsel', '')),
        ];
        $aradiMi = $f['il_id'] || $f['ilce_id'] || $f['mahalle_id'] || $f['ada'] !== '' || $f['parsel'] !== '';

        $sonuclar = $aradiMi ? Tasinmaz::query()
            ->with(['ilce:id,ad', 'mahalle:id,ad', 'yapi', 'degerlemeler:id,tasinmaz_id,tip,toplam_bedel,rapor_tarihi'])
            ->withCount('yapilar')
            ->when($f['il_id'], fn ($s, $v) => $s->where('il_id', $v))
            ->when($f['ilce_id'], fn ($s, $v) => $s->where('ilce_id', $v))
            ->when($f['mahalle_id'], fn ($s, $v) => $s->where('mahalle_id', $v))
            ->when($f['ada'] !== '', fn ($s) => $s->where('ada', $f['ada']))
            ->when($f['parsel'] !== '', fn ($s) => $s->where('parsel', $f['parsel']))
            ->orderBy('ada')->orderBy('parsel')->orderBy('id')
            ->paginate(20)->withQueryString() : null;

        return view('panel.degerleme.yeni', [
            'iller' => Il::orderBy('ad')->get(['id', 'ad']),
            'ilceler' => $f['il_id'] ? Ilce::where('il_id', $f['il_id'])->orderBy('ad')->get(['id', 'ad']) : collect(),
            'mahalleler' => $f['ilce_id'] ? Mahalle::where('ilce_id', $f['ilce_id'])->orderBy('ad')->get(['id', 'ad']) : collect(),
            'filtre' => $f,
            'aradiMi' => $aradiMi,
            'sonuclar' => $sonuclar,
        ]);
    }

    public function olustur(Request $request, int $tasinmazId): View
    {
        $tasinmaz = $this->tasinmazYukle($tasinmazId);
        $tipler = TasinmazDegerleme::tipler($tasinmaz);
        $istenen = $request->query('tip');

        $kayit = new TasinmazDegerleme([
            'tip' => in_array($istenen, $tipler, true) ? $istenen : $tipler[0],
            'hazirlayan_id' => auth()->id(),
            'rapor_tarihi' => now(),
            'hisse_alan' => TasinmazDegerleme::kurumHisseAlani($tasinmaz),
        ]);
        if ($tasinmaz->kayit_tipi === KayitTipi::KatMulkiyetli && $tasinmaz->yapilar->count() === 1) {
            $this->yapidanDoldur($kayit, $tasinmaz->yapilar->first());
        }

        return $this->formGoster($kayit, $tasinmaz);
    }

    public function kaydet(Request $request, int $tasinmazId): RedirectResponse
    {
        $tasinmaz = $this->tasinmazYukle($tasinmazId);
        $veri = $this->dogrula($request, $tasinmaz);
        $veri['tasinmaz_id'] = $tasinmaz->id;
        $veri['olusturan_id'] = auth()->id();
        if ($request->hasFile('evrak')) {
            $veri['evrak'] = $request->file('evrak')->store(self::EVRAK_KLASOR.'/'.now()->format('Y/m'), 'public');
        }

        $kayit = TasinmazDegerleme::create($veri);

        return redirect()->route('panel.degerleme.detay', $kayit->id)->with('basari', 'Değerleme kaydedildi.');
    }

    public function detay(int $id): View
    {
        $kayit = TasinmazDegerleme::with(['hazirlayan:id,ad,soyad', 'olusturan:id,ad,soyad', 'yapi'])->findOrFail($id);
        $tasinmaz = $this->tasinmazYukle($kayit->tasinmaz_id);

        return view('panel.degerleme.detay', [
            'kayit' => $kayit,
            'tasinmaz' => $tasinmaz,
            // Aynı taşınmazın diğer değerlemeleri — değer geçmişi
            'gecmis' => $tasinmaz->degerlemeler()->with('hazirlayan:id,ad,soyad')->get(),
        ]);
    }

    /** Yazdırılabilir değerleme raporu (tarayıcıdan "PDF olarak kaydet"). */
    public function rapor(int $id): View
    {
        $kayit = TasinmazDegerleme::with(['hazirlayan:id,ad,soyad'])->findOrFail($id);

        return view('panel.degerleme.rapor', [
            'kayit' => $kayit,
            'tasinmaz' => $this->tasinmazYukle($kayit->tasinmaz_id),
        ]);
    }

    public function duzenle(int $id): View
    {
        $kayit = TasinmazDegerleme::findOrFail($id);

        return $this->formGoster($kayit, $this->tasinmazYukle($kayit->tasinmaz_id));
    }

    public function guncelle(Request $request, int $id): RedirectResponse
    {
        $kayit = TasinmazDegerleme::findOrFail($id);
        $tasinmaz = $this->tasinmazYukle($kayit->tasinmaz_id);
        $veri = $this->dogrula($request, $tasinmaz, $kayit);

        $eski = $kayit->evrak;
        if ($request->hasFile('evrak')) {
            $veri['evrak'] = $request->file('evrak')->store(self::EVRAK_KLASOR.'/'.now()->format('Y/m'), 'public');
        } elseif ($request->boolean('evrak_kaldir')) {
            $veri['evrak'] = null;
        }
        $kayit->update($veri);
        if ($eski && $kayit->evrak !== $eski) {
            $this->evrakDosyasiniSil($eski);
        }

        return redirect()->route('panel.degerleme.detay', $kayit->id)->with('basari', 'Değerleme güncellendi.');
    }

    public function sil(int $id): RedirectResponse
    {
        TasinmazDegerleme::findOrFail($id)->delete(); // evrak: model kancası

        return redirect()->route('panel.degerleme.index')->with('basari', 'Değerleme silindi.');
    }

    // ------------------------------------------------------------------
    // Harita
    // ------------------------------------------------------------------

    public function harita(Request $request): View
    {
        $yillar = TasinmazDegerleme::whereNotNull('rapor_tarihi')->pluck('rapor_tarihi')
            ->map(fn ($t) => (int) $t->format('Y'))->unique()->sortDesc()->values();

        // Katman listesi yalnızca haritada karşılığı olan imar durumlarını gösterir;
        // 97 tanımın tamamı panele sığmaz ve çoğu boş çıkar.
        $emsalGorebilir = $request->user()->izinVarMi('emsal.goruntule');
        $kullanilan = TasinmazImar::query()
            ->whereNotNull('imar_durumu_id')
            ->whereIn('tasinmaz_id', TasinmazDegerleme::query()->select('tasinmaz_id'))
            ->distinct()->pluck('imar_durumu_id');
        if ($emsalGorebilir) {
            $kullanilan = $kullanilan->merge(
                EmsalParsel::query()->whereNotNull('imar_durumu_id')->distinct()->pluck('imar_durumu_id')
            )->unique();
        }

        return view('panel.degerleme.harita', [
            'iller' => Il::orderBy('ad')->get(['id', 'ad']),
            'yillar' => $yillar,
            'emsalGorebilir' => $emsalGorebilir,
            'imarDurumlari' => ImarDurumu::whereIn('id', $kullanilan)->orderBy('sira')->orderBy('ad')->get(['id', 'ad']),
        ]);
    }

    /**
     * Değerlenmiş taşınmazlar (taşınmaz başına bir öğe). properties.tur:
     * arsa | bb | ikisi — süzgeçten geçen değerlemelere göre.
     */
    public function haritaGeojson(Request $request): JsonResponse
    {
        $tip = in_array($request->query('tip'), ['arsa', 'bb'], true) ? $request->query('tip') : null;
        $yil = preg_match('/^\d{4}$/', (string) $request->query('yil')) ? (int) $request->query('yil') : null;
        $q = trim((string) $request->query('q', ''));
        $ada = trim((string) $request->query('ada', ''));
        $parsel = trim((string) $request->query('parsel', ''));
        $degSuz = fn ($d) => $d->when($tip, fn ($x) => $x->where('tip', $tip))
            ->when($yil, fn ($x) => $x->whereBetween('rapor_tarihi', [$yil.'-01-01', $yil.'-12-31']))
            ->when($q !== '', fn ($x) => $x->where(fn ($y) => $y->where('rapor_no', 'like', "%{$q}%")->orWhere('bb_no', $q)));

        $tasinmazId = (int) $request->query('tasinmaz_id');
        $ilId = $request->query('il_id');
        $ilceId = $request->query('ilce_id');
        $mahalleId = $request->query('mahalle_id');
        // ?imar=3,7,yok — haritadaki imar durumu katmanları. Kümeler sunucuda
        // hesaplandığı için bu süzgeç istemcide değil burada uygulanmalı.
        $imar = $this->imarSuzgeci($request);
        $tasSuz = fn ($s) => $s->when($tasinmazId, fn ($x) => $x->where('id', $tasinmazId))
            ->when($ilId, fn ($x) => $x->where('il_id', $ilId))
            ->when($ilceId, fn ($x) => $x->where('ilce_id', $ilceId))
            ->when($mahalleId, fn ($x) => $x->where('mahalle_id', $mahalleId))
            ->when($ada !== '', fn ($x) => $x->where('ada', $ada))
            ->when($parsel !== '', fn ($x) => $x->where('parsel', $parsel))
            ->when($imar !== null, fn ($x) => $x->where(function ($w) use ($imar) {
                if ($imar['idler'] !== []) {
                    $w->whereIn('id', TasinmazImar::query()->select('tasinmaz_id')->whereIn('imar_durumu_id', $imar['idler']));
                }
                if ($imar['yok']) {
                    $w->orWhereNotIn('id', TasinmazImar::query()->select('tasinmaz_id')->whereNotNull('imar_durumu_id'));
                }
            }));
        $tasSuzVar = $tasinmazId || $ilId || $ilceId || $mahalleId || $ada !== '' || $parsel !== '' || $imar !== null;

        // Süzgeçten geçen değerlemeler; taşınmaz süzgeci yalnızca gerekiyorsa eklenir
        // (100 bin taşınmazı tek tek "değerlemesi var mı" diye taramamak için)
        $degerlemeler = TasinmazDegerleme::query()->tap($degSuz)
            ->when($tasSuzVar, fn ($d) => $d->whereIn('tasinmaz_id', Tasinmaz::query()->tap($tasSuz)->select('id')));
        $idler = (clone $degerlemeler)->select('tasinmaz_id');

        // Görünen alan (?bbox=&zoom=): kalabalık alanda kümeler; ?kapsam=1: sınır + m² değer aralığı
        $koordinatlar = TasinmazKoordinat::query()->whereIn('tasinmaz_id', $idler);
        if ($request->boolean('kapsam')) {
            $birim = (clone $degerlemeler)->where('tip', 'arsa')->where('birim_fiyat', '>', 0)
                ->selectRaw('MIN(birim_fiyat) AS mn, MAX(birim_fiyat) AS mx')->toBase()->first();

            return response()->json(HaritaGorunum::kapsam($koordinatlar) + [
                'birim_aralik' => $birim && $birim->mn !== null ? [(float) $birim->mn, (float) $birim->mx] : null,
            ]);
        }
        $bbox = HaritaGorunum::bbox($request);
        if ($bbox && ($kume = HaritaGorunum::kumeler($koordinatlar, $bbox, HaritaGorunum::zoom($request)))) {
            return response()->json(['type' => 'FeatureCollection', 'kumeli' => true] + $kume);
        }

        $tasinmazlar = Tasinmaz::query()->whereIn('id', $idler)->tap($tasSuz)
            ->with(['ilce:id,ad', 'mahalle:id,ad', 'koordinat', 'imar.imarDurumu:id,ad', 'degerlemeler' => $degSuz])
            ->when($bbox,
                fn ($s) => $s->whereIn('id', TasinmazKoordinat::query()->alanda($bbox)->select('tasinmaz_id')),
                fn ($s) => $s->whereHas('koordinat'))
            ->get(['id', 'il_id', 'ilce_id', 'mahalle_id', 'ada', 'parsel', 'alan', 'nitelik', 'kayit_tipi']);

        $features = [];
        foreach ($tasinmazlar as $t) {
            $geo = $t->koordinat?->geoJson();
            if (! $geo) {
                continue;
            }
            $d = $t->degerlemeler; // en yeni önce
            $arsa = $d->where('tip', 'arsa')->count();
            $bb = $d->where('tip', 'bb')->count();
            $son = $d->first();
            $sonArsa = $d->firstWhere('tip', 'arsa');
            $arsaAlan = $sonArsa?->degerlenenAlan();
            $features[] = [
                'type' => 'Feature',
                'id' => $t->id,
                'geometry' => $geo,
                'properties' => [
                    'id' => $t->id,
                    'ada' => $t->ada,
                    'parsel' => $t->parsel,
                    'ilce' => $t->ilce?->ad,
                    'mahalle' => $t->mahalle?->ad,
                    'alan' => $t->alan !== null ? (float) $t->alan : null,
                    'imar_id' => $t->imar?->imar_durumu_id,
                    'imar' => $t->imar?->imarDurumu?->ad,
                    'tur' => $arsa && $bb ? 'ikisi' : ($bb ? 'bb' : 'arsa'),
                    'arsa_sayisi' => $arsa,
                    'bb_sayisi' => $bb,
                    'son_toplam' => (float) $son->toplam_bedel,
                    'son_tarih' => $son->rapor_tarihi?->toDateString(),
                    // Arsa m² birim değeri (girilmemişse toplam / alan)
                    'birim' => $sonArsa ? round((float) ($sonArsa->birim_fiyat ?? ($arsaAlan ? $sonArsa->toplam_bedel / $arsaAlan : 0)), 2) : null,
                ],
            ];
        }

        return response()->json(['type' => 'FeatureCollection', 'kumeli' => false, 'toplam' => count($features), 'features' => $features]);
    }

    /**
     * ?imar=3,7,yok → ['idler' => [3, 7], 'yok' => true]. Parametre hiç
     * gelmezse null döner (süzgeç uygulanmaz). Hepsi kapalıysa idler boş ve
     * yok false olur; bu da "hiçbir şey gösterme" demektir.
     *
     * @return array{idler: array<int, int>, yok: bool}|null
     */
    public static function imarSuzgeci(Request $request): ?array
    {
        $ham = $request->query('imar');
        if ($ham === null) {
            return null;
        }

        $parcalar = array_filter(array_map('trim', explode(',', (string) $ham)), fn ($v) => $v !== '');

        return [
            'idler' => array_values(array_map('intval', array_filter($parcalar, 'is_numeric'))),
            'yok' => in_array('yok', $parcalar, true),
        ];
    }

    /** Harita yan paneli: taşınmaz özeti + tüm değerlemeleri. */
    public function haritaTasinmaz(int $tasinmazId): JsonResponse
    {
        $t = $this->tasinmazYukle($tasinmazId);
        $k = auth()->user();

        return response()->json([
            'tasinmaz' => [
                'id' => $t->id,
                'il' => $t->il?->ad, 'ilce' => $t->ilce?->ad, 'mahalle' => $t->mahalle?->ad,
                'ada' => $t->ada, 'parsel' => $t->parsel,
                'alan' => $t->alan !== null ? (float) $t->alan : null,
                'nitelik' => $t->nitelik,
                'kayit_tipi' => $t->kayit_tipi?->etiket(),
                'imar' => $t->imar?->imarDurumu?->ad,
                'emsal' => $t->imar?->emsal !== null ? (float) $t->imar->emsal : null,
                'yencok' => $t->imar?->yenaz_yencok,
                'kurum_hisse' => TasinmazDegerleme::kurumHisseAlani($t),
                'detay_url' => $k->izinVarMi('tasinmaz.goruntule') ? $t->rota('detay') : null,
            ],
            'degerlemeler' => $t->degerlemeler()->with('hazirlayan:id,ad,soyad')->get()->map(fn ($d) => [
                'id' => $d->id,
                'tip' => $d->tip,
                'bolum' => $d->bolumEtiketi(),
                'alan' => $d->degerlenenAlan(),
                'birim_fiyat' => $d->birim_fiyat !== null ? (float) $d->birim_fiyat : null,
                'toplam' => (float) $d->toplam_bedel,
                'rapor_no' => $d->rapor_no,
                'rapor_tarihi' => $d->rapor_tarihi?->toDateString(),
                'hazirlayan' => $d->hazirlayan ? $d->hazirlayan->ad.' '.$d->hazirlayan->soyad : null,
                'detay_url' => route('panel.degerleme.detay', $d->id),
                'rapor_url' => route('panel.degerleme.rapor', $d->id),
                'pdf_url' => $d->evrak ? asset('storage/'.$d->evrak) : null,
            ]),
            'yeni' => $k->izinVarMi('degerleme.olustur') ? collect(TasinmazDegerleme::tipler($t))->mapWithKeys(fn ($tip) => [
                $tip => route('panel.degerleme.olustur', ['tasinmazId' => $t->id, 'tip' => $tip]),
            ]) : (object) [],
        ]);
    }

    // ------------------------------------------------------------------
    // Excel ile toplu içe aktarım
    // ------------------------------------------------------------------

    /** A–P kolonları; şablon ve okuma aynı sırayı kullanır. */
    private const KOLONLAR = [
        'A' => 'İlçe *', 'B' => 'Mahalle *', 'C' => 'Ada *', 'D' => 'Parsel *',
        'E' => 'BB No (boşsa arsa)', 'F' => 'Kat', 'G' => 'BB Nitelik', 'H' => 'Oda Sayısı', 'I' => 'Brüt Alan (m²)',
        'J' => 'Hisse Alan (m²)', 'K' => 'Birim Fiyat (TL/m²)', 'L' => 'Toplam Bedel (TL) *',
        'M' => 'Rapor No', 'N' => 'Rapor Tarihi', 'O' => 'Hazırlayan (kullanıcı adı)', 'P' => 'Açıklama',
    ];

    public function iceAktar(): View
    {
        return view('panel.degerleme.ice-aktar', [
            'kolonlar' => self::KOLONLAR,
            'evraksizSayi' => TasinmazDegerleme::whereNull('evrak')->whereNotNull('rapor_no')->count(),
        ]);
    }

    public function sablonIndir(): StreamedResponse
    {
        $ss = new Spreadsheet;
        $sh = $ss->getActiveSheet();
        $sh->setTitle('Değerlemeler');
        foreach (self::KOLONLAR as $harf => $ad) {
            $sh->setCellValue($harf.'1', $ad);
            $sh->getColumnDimension($harf)->setWidth(in_array($harf, ['A', 'B', 'O', 'P'], true) ? 22 : 15);
        }
        $sh->getStyle('A1:P1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0D9488']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
        $sh->getRowDimension(1)->setRowHeight(34);
        $sh->freezePane('A2');

        $not = $ss->createSheet();
        $not->setTitle('Açıklama');
        foreach ([
            'İlk sayfadaki her satır bir değerleme raporudur; başlık satırı dışındaki tüm satırlar içe aktarılır.',
            'Taşınmaz sistemde kayıtlı olmalıdır (İlçe + Mahalle + Ada + Parsel ile bulunur). Değerleme taşınmaz oluşturmaz.',
            'BB No boşsa ARSA değerlemesi; doluysa o bağımsız bölümün değerlemesi açılır (yapı kayıtlarındaki BB No ile eşleşir).',
            'Hisse Alan boşsa kurumun aktif hisse toplamı alınır. Toplam Bedel boşsa Birim Fiyat × alan ile hesaplanır.',
            'Sayılar 1.750.000,00 ya da 1750000.00 biçiminde; tarih 15.01.2026 biçiminde yazılabilir.',
            'Aynı taşınmaz + tür + BB No + Rapor No ile kayıt zaten varsa satır atlanır (yinelenmez).',
            'Rapor PDF’lerini daha sonra "Rapor PDF’lerini eşleştir" ile yükleyin: dosya adı rapor no olmalı (2026/15 → 2026-15.pdf).',
        ] as $i => $satir) {
            $not->setCellValue('A'.($i + 1), $satir);
        }
        $not->getColumnDimension('A')->setWidth(120);
        $ss->setActiveSheetIndex(0);

        return response()->streamDownload(fn () => (new Xlsx($ss))->save('php://output'), 'degerleme-sablon.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function iceAktarYukle(Request $request): RedirectResponse
    {
        $request->validate(['dosya' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480']], [], ['dosya' => 'Excel dosyası']);

        $satirlar = IOFactory::load($request->file('dosya')->getPathname())->getSheet(0)->toArray(null, true, false, true);
        array_shift($satirlar); // başlık

        $kullanicilar = Kullanici::get(['id', 'kullanici_adi', 'ad', 'soyad']);
        $eklenen = 0;
        $atlanan = 0;
        $hatalar = [];
        foreach ($satirlar as $no => $s) {
            if (! array_filter($s, fn ($v) => filled($v))) {
                continue;
            }
            try {
                $veri = $this->excelSatiri($s, $kullanicilar);
                $tekrar = TasinmazDegerleme::where('tasinmaz_id', $veri['tasinmaz_id'])->where('tip', $veri['tip'])
                    ->where('bb_no', $veri['bb_no'])->where('rapor_no', $veri['rapor_no'])->exists();
                if ($tekrar) {
                    $atlanan++;

                    continue;
                }
                TasinmazDegerleme::create($veri + ['olusturan_id' => auth()->id()]);
                $eklenen++;
            } catch (\RuntimeException $e) {
                $hatalar[] = "Satır {$no}: ".$e->getMessage();
            }
        }

        $mesaj = "{$eklenen} değerleme eklendi".($atlanan ? ", {$atlanan} satır zaten kayıtlı olduğu için atlandı" : '').($hatalar ? ', '.count($hatalar).' satır işlenemedi' : '').'.';

        return redirect()->route('panel.degerleme.ice-aktar')
            ->with($eklenen || $atlanan ? 'basari' : 'hata', $mesaj)
            ->with('import_hatalari', $hatalar);
    }

    /**
     * Toplu rapor PDF yükleme: dosya adı rapor no ile eşleşen ve evrakı
     * olmayan değerlemelere bağlanır (2026/15 → 2026-15.pdf). Aynı rapor
     * numaralı birden çok kayıt (ör. bir binanın BB'leri) aynı dosyayı paylaşır.
     */
    public function evrakEslestir(Request $request): RedirectResponse
    {
        $request->validate([
            'dosyalar' => ['required', 'array', 'max:200'],
            'dosyalar.*' => ['file', 'mimes:pdf', 'max:20480'],
            'uzerine_yaz' => ['nullable', 'boolean'],
        ], [], ['dosyalar' => 'PDF dosyaları', 'dosyalar.*' => 'PDF dosyası']);

        $uzerineYaz = $request->boolean('uzerine_yaz');
        $adaylar = TasinmazDegerleme::whereNotNull('rapor_no')->when(! $uzerineYaz, fn ($q) => $q->whereNull('evrak'))
            ->get(['id', 'rapor_no', 'evrak'])->groupBy(fn ($d) => $this->raporAnahtari($d->rapor_no));

        $eslesen = [];
        $eslesmeyen = [];
        foreach ($request->file('dosyalar') as $dosya) {
            $ad = pathinfo($dosya->getClientOriginalName(), PATHINFO_FILENAME);
            $kayitlar = $adaylar->get($this->raporAnahtari($ad));
            if (! $kayitlar) {
                $eslesmeyen[] = $dosya->getClientOriginalName();

                continue;
            }
            $yol = $dosya->store(self::EVRAK_KLASOR.'/'.now()->format('Y/m'), 'public');
            foreach ($kayitlar as $k) {
                $eski = $k->evrak;
                $k->update(['evrak' => $yol]);
                if ($eski && $eski !== $yol) {
                    $this->evrakDosyasiniSil($eski);
                }
            }
            $eslesen[] = $dosya->getClientOriginalName().' → '.$kayitlar->count().' kayıt';
        }

        return redirect()->route('panel.degerleme.ice-aktar')
            ->with($eslesen ? 'basari' : 'hata', count($eslesen).' PDF eşleşti'.($eslesmeyen ? ', '.count($eslesmeyen).' PDF için rapor no bulunamadı' : '').'.')
            ->with('import_hatalari', array_map(fn ($a) => "Eşleşmedi: {$a}", $eslesmeyen));
    }

    // ------------------------------------------------------------------
    // Yardımcılar
    // ------------------------------------------------------------------

    private function tasinmazYukle(int $id): Tasinmaz
    {
        return Tasinmaz::with([
            'il:id,ad', 'ilce:id,ad', 'mahalle:id,ad,tkgm_id',
            'imar.imarDurumu:id,ad', 'hisseler', 'tapu',
            'yapilar' => fn ($y) => $y->orderBy('sira')->orderBy('id'),
        ])->findOrFail($id);
    }

    private function formGoster(TasinmazDegerleme $kayit, Tasinmaz $tasinmaz): View
    {
        return view('panel.degerleme.form', [
            'kayit' => $kayit,
            'tasinmaz' => $tasinmaz,
            'tipler' => TasinmazDegerleme::tipler($tasinmaz),
            'kurumHisse' => TasinmazDegerleme::kurumHisseAlani($tasinmaz),
            'kullanicilar' => Kullanici::where('aktif_mi', true)->orWhere('id', $kayit->hazirlayan_id)
                ->orderBy('ad')->orderBy('soyad')->get(['id', 'ad', 'soyad']),
        ]);
    }

    private function dogrula(Request $request, Tasinmaz $tasinmaz, ?TasinmazDegerleme $kayit = null): array
    {
        $veri = $request->validate([
            'tip' => ['required', Rule::in(TasinmazDegerleme::tipler($tasinmaz))],
            'yapi_id' => ['nullable', 'integer', Rule::exists('tasinmaz_yapi', 'id')->where('tasinmaz_id', $tasinmaz->id)],
            'bb_no' => ['nullable', 'string', 'max:30'],
            'kat' => ['nullable', 'string', 'max:30'],
            'bb_nitelik' => ['nullable', 'string', 'max:100'],
            'oda_sayisi' => ['nullable', 'string', 'max:20'],
            'brut_alan' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'hisse_alan' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'birim_fiyat' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'toplam_bedel' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999', 'required_without:birim_fiyat'],
            'rapor_no' => ['nullable', 'string', 'max:60'],
            'rapor_tarihi' => ['nullable', 'date', 'before_or_equal:'.now()->addDay()->toDateString()],
            'hazirlayan_id' => ['nullable', 'integer', 'exists:kullanicilar,id'],
            'aciklama' => ['nullable', 'string', 'max:5000'],
            'evrak' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
        ], [
            'tip.in' => 'Bu taşınmazın kayıt tipi için seçilen değerleme türü açılamaz.',
            'toplam_bedel.required_without' => 'Toplam bedel ya da birim fiyat girilmeli.',
            'rapor_tarihi.before_or_equal' => 'Rapor tarihi ileri bir tarih olamaz.',
        ], [
            'brut_alan' => 'brüt alan', 'hisse_alan' => 'hisse alanı', 'birim_fiyat' => 'birim fiyat',
            'toplam_bedel' => 'toplam bedel', 'rapor_tarihi' => 'rapor tarihi', 'evrak' => 'rapor PDF',
        ]);
        unset($veri['evrak']);

        if ($veri['tip'] === 'bb') {
            if (! filled($veri['bb_no'] ?? null) && empty($veri['yapi_id'])) {
                throw \Illuminate\Validation\ValidationException::withMessages(['bb_no' => 'Bağımsız bölüm değerlemesinde bölüm seçilmeli ya da BB No girilmeli.']);
            }
            $veri['hisse_alan'] = null;
        } else {
            foreach (['yapi_id', 'bb_no', 'kat', 'bb_nitelik', 'oda_sayisi', 'brut_alan'] as $a) {
                $veri[$a] = null;
            }
        }

        // Toplam boşsa birim fiyat × alan
        if (! filled($veri['toplam_bedel'] ?? null)) {
            $alan = $veri['tip'] === 'bb' ? ($veri['brut_alan'] ?? null) : ($veri['hisse_alan'] ?? $tasinmaz->alan);
            if (! filled($alan)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['toplam_bedel' => 'Toplam bedeli hesaplamak için alan bilgisi yok; toplam bedeli girin.']);
            }
            $veri['toplam_bedel'] = round((float) $veri['birim_fiyat'] * (float) $alan, 2);
        }

        return $veri;
    }

    private function yapidanDoldur(TasinmazDegerleme $kayit, $yapi): void
    {
        $kayit->fill([
            'yapi_id' => $yapi->id,
            'bb_no' => $yapi->bagimsiz_bolum_no,
            'kat' => $yapi->kat_no !== null ? (string) $yapi->kat_no : null,
            'bb_nitelik' => $yapi->nitelik,
            'oda_sayisi' => $yapi->oda_sayisi !== null ? (string) $yapi->oda_sayisi : null,
            'brut_alan' => $yapi->brut_alan,
        ]);
    }

    private function donemSuz($sorgu, string $donem)
    {
        $bas = match ($donem) {
            'bugun' => now()->startOfDay(),
            'hafta' => now()->startOfWeek(),
            default => now()->startOfMonth(),
        };

        return $sorgu->where('created_at', '>=', $bas);
    }

    /** Aynı evrak birden çok kayıtta kullanılabilir; son kullanan da gidince silinir. */
    private function evrakDosyasiniSil(?string $yol): void
    {
        if ($yol && ! TasinmazDegerleme::where('evrak', $yol)->exists()) {
            Storage::disk('public')->delete($yol);
        }
    }

    /** "2026/15", "2026-15", "2026 15" → "2026-15" (büyük/küçük harf duyarsız). */
    private function raporAnahtari(string $s): string
    {
        return trim(preg_replace('~[\s/\\\\_.-]+~u', '-', mb_strtolower($this->asciiyeCevir($s), 'UTF-8')) ?? '', '-');
    }

    private function asciiyeCevir(string $s): string
    {
        return strtr($s, ['ç' => 'c', 'Ç' => 'c', 'ğ' => 'g', 'Ğ' => 'g', 'ı' => 'i', 'I' => 'i', 'İ' => 'i', 'ö' => 'o', 'Ö' => 'o', 'ş' => 's', 'Ş' => 's', 'ü' => 'u', 'Ü' => 'u']);
    }

    private function adAnahtari(?string $s): string
    {
        return preg_replace('~[^a-z0-9]+~', '', strtolower($this->asciiyeCevir(trim((string) $s)))) ?? '';
    }

    /**
     * Excel satırını doğrulanmış değerleme verisine çevirir; taşınmazı
     * İlçe + Mahalle + Ada + Parsel (+ BB No) ile bulur.
     */
    private function excelSatiri(array $s, Collection $kullanicilar): array
    {
        $deger = fn (string $k) => trim((string) ($s[$k] ?? ''));
        [$ilceAd, $mahalleAd, $ada, $parsel, $bbNo] = [$deger('A'), $deger('B'), $deger('C'), $deger('D'), $deger('E')];
        if ($ilceAd === '' || $mahalleAd === '' || $ada === '' || $parsel === '') {
            throw new \RuntimeException('İlçe, Mahalle, Ada ve Parsel zorunlu.');
        }

        $ilce = Ilce::get(['id', 'ad'])->first(fn ($i) => $this->adAnahtari($i->ad) === $this->adAnahtari($ilceAd));
        if (! $ilce) {
            throw new \RuntimeException("İlçe bulunamadı: {$ilceAd}");
        }
        $mahalle = Mahalle::where('ilce_id', $ilce->id)->get(['id', 'ad'])->first(fn ($m) => $this->adAnahtari($m->ad) === $this->adAnahtari($mahalleAd));
        if (! $mahalle) {
            throw new \RuntimeException("Mahalle bulunamadı: {$ilceAd} / {$mahalleAd}");
        }

        $adaylar = Tasinmaz::with('yapilar:id,tasinmaz_id,bagimsiz_bolum_no,kat_no,nitelik,oda_sayisi,brut_alan', 'hisseler')
            ->where('mahalle_id', $mahalle->id)->where('ada', $ada)->where('parsel', $parsel)->get();
        if ($adaylar->isEmpty()) {
            throw new \RuntimeException("Taşınmaz kayıtlı değil: {$mahalle->ad} {$ada}/{$parsel}");
        }

        $yapi = null;
        if ($bbNo === '') {
            $tip = 'arsa';
            $arsalar = $adaylar->filter(fn ($t) => in_array('arsa', TasinmazDegerleme::tipler($t), true));
            if ($arsalar->count() !== 1) {
                throw new \RuntimeException($arsalar->isEmpty()
                    ? "{$ada}/{$parsel} yalnız bağımsız bölüm kayıtlarından oluşuyor; BB No girin."
                    : "{$ada}/{$parsel} için birden çok arsa kaydı var; elle girin.");
            }
            $tasinmaz = $arsalar->first();
        } else {
            $tip = 'bb';
            $bbAnahtar = $this->adAnahtari($bbNo);
            $eslesme = $adaylar->flatMap(fn ($t) => $t->yapilar->map(fn ($y) => [$t, $y]))
                ->filter(fn ($c) => $this->adAnahtari((string) $c[1]->bagimsiz_bolum_no) === $bbAnahtar)->values();
            if ($eslesme->count() > 1) {
                throw new \RuntimeException("{$ada}/{$parsel} BB {$bbNo} birden çok kayıtla eşleşti; elle girin.");
            }
            if ($eslesme->count() === 1) {
                [$tasinmaz, $yapi] = $eslesme->first();
            } else {
                // Yapı listesi hiç girilmemiş bina kaydına elle bölüm olarak yazılır;
                // yapı listesi olan binada eşleşmeyen BB No yazım hatası sayılır.
                $binalar = $adaylar->filter(fn ($t) => $t->kayit_tipi === KayitTipi::KatMulkiyetsizBina && $t->yapilar->isEmpty());
                if ($binalar->count() !== 1) {
                    throw new \RuntimeException("{$ada}/{$parsel} için BB {$bbNo} yapı kayıtlarında bulunamadı.");
                }
                $tasinmaz = $binalar->first();
            }
        }

        $sayi = fn (string $k) => $this->trSayi($s[$k] ?? null);
        $brut = $sayi('I') ?? ($yapi?->brut_alan !== null ? (float) $yapi->brut_alan : null);
        $hisse = $tip === 'arsa' ? ($sayi('J') ?? TasinmazDegerleme::kurumHisseAlani($tasinmaz)) : null;
        $birim = $sayi('K');
        $toplam = $sayi('L');
        if ($toplam === null) {
            $alan = $tip === 'bb' ? $brut : $hisse;
            if ($birim === null || $alan === null) {
                throw new \RuntimeException('Toplam Bedel ya da Birim Fiyat + alan gerekli.');
            }
            $toplam = round($birim * $alan, 2);
        }

        $hazirlayanId = null;
        if (($h = $deger('O')) !== '') {
            $k = $kullanicilar->first(fn ($u) => mb_strtolower($u->kullanici_adi) === mb_strtolower(ltrim($h, '@'))
                || $this->adAnahtari($u->ad.' '.$u->soyad) === $this->adAnahtari($h));
            if (! $k) {
                throw new \RuntimeException("Hazırlayan kullanıcı bulunamadı: {$h}");
            }
            $hazirlayanId = $k->id;
        }

        return [
            'tasinmaz_id' => $tasinmaz->id,
            'tip' => $tip,
            'yapi_id' => $yapi?->id,
            'bb_no' => $tip === 'bb' ? ($yapi?->bagimsiz_bolum_no ?? $bbNo) : null,
            'kat' => $tip === 'bb' ? (($deger('F') ?: null) ?? ($yapi?->kat_no !== null ? (string) $yapi->kat_no : null)) : null,
            'bb_nitelik' => $tip === 'bb' ? (($deger('G') ?: null) ?? $yapi?->nitelik) : null,
            'oda_sayisi' => $tip === 'bb' ? (($deger('H') ?: null) ?? ($yapi?->oda_sayisi !== null ? (string) $yapi->oda_sayisi : null)) : null,
            'brut_alan' => $tip === 'bb' ? $brut : null,
            'hisse_alan' => $hisse,
            'birim_fiyat' => $birim,
            'toplam_bedel' => $toplam,
            'rapor_no' => $deger('M') ?: null,
            'rapor_tarihi' => $this->tarih($s['N'] ?? null),
            'hazirlayan_id' => $hazirlayanId,
            'aciklama' => $deger('P') ?: null,
        ];
    }

    private function trSayi(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        $s = preg_replace('/[\s\x{00A0}₺]|TL/u', '', (string) $v) ?? '';
        if (preg_match('/^(.+)[.,](\d{1,2})$/', $s, $m)) {
            $s = str_replace(['.', ','], '', $m[1]).'.'.$m[2];
        } else {
            $s = str_replace(['.', ','], '', $s);
        }
        if (! is_numeric($s)) {
            throw new \RuntimeException("Sayı okunamadı: {$v}");
        }

        return (float) $s;
    }

    private function tarih(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v) && $v > 20000 && $v < 80000) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $v)->format('Y-m-d');
        }
        foreach (['d.m.Y', 'd/m/Y', 'Y-m-d', 'd.m.y'] as $bicim) {
            $t = \DateTime::createFromFormat('!'.$bicim, trim((string) $v));
            if ($t) {
                return $t->format('Y-m-d');
            }
        }
        throw new \RuntimeException("Tarih okunamadı: {$v}");
    }

    private function excelYaz(Collection $kayitlar): StreamedResponse
    {
        $ss = new Spreadsheet;
        $sh = $ss->getActiveSheet();
        $sh->setTitle('Değerlemeler');
        $basliklar = ['Tür', 'İlçe', 'Mahalle', 'Ada', 'Parsel', 'BB No', 'Kat', 'BB Nitelik', 'Oda', 'Brüt Alan (m²)', 'Parsel Alanı (m²)', 'Hisse Alan (m²)', 'Birim Fiyat (TL/m²)', 'Toplam Bedel (TL)', 'Rapor No', 'Rapor Tarihi', 'Hazırlayan', 'Evrak'];
        $sh->fromArray($basliklar, null, 'A1');
        $son = 'R';
        $sh->getStyle("A1:{$son}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0D9488']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);

        $r = 2;
        foreach ($kayitlar as $k) {
            $t = $k->tasinmaz;
            $sh->fromArray([
                $k->tip === 'bb' ? 'Bağımsız Bölüm' : 'Arsa',
                $t?->ilce?->ad, $t?->mahalle?->ad, $t?->ada, $t?->parsel,
                $k->bb_no, $k->kat, $k->bb_nitelik, $k->oda_sayisi,
                $k->brut_alan !== null ? (float) $k->brut_alan : null,
                $t?->alan !== null ? (float) $t->alan : null,
                $k->hisse_alan !== null ? (float) $k->hisse_alan : null,
                $k->birim_fiyat !== null ? (float) $k->birim_fiyat : null,
                (float) $k->toplam_bedel,
                $k->rapor_no, $k->rapor_tarihi?->format('d.m.Y'),
                $k->hazirlayan ? $k->hazirlayan->ad.' '.$k->hazirlayan->soyad : null,
                $k->evrak ? 'Var' : 'Yok',
            ], null, 'A'.$r);
            $r++;
        }
        $sh->getStyle('J2:N'.max(2, $r - 1))->getNumberFormat()->setFormatCode('#,##0.00');
        foreach (range('A', $son) as $c) {
            $sh->getColumnDimension($c)->setAutoSize(true);
        }
        $sh->freezePane('A2');
        $sh->setAutoFilter("A1:{$son}".max(1, $r - 1));

        return response()->streamDownload(fn () => (new Xlsx($ss))->save('php://output'), 'degerlemeler-'.now()->format('Y-m-d').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
