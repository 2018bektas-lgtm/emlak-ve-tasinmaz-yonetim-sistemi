<?php

namespace App\Http\Controllers;

use App\Enums\EmsalTipi;
use App\Models\Emsal;
use App\Models\EmsalParsel;
use App\Models\Il;
use App\Models\Ilce;
use App\Models\ImarDurumu;
use App\Models\Mahalle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Emsal (karşılaştırılabilir satış) kayıtları: encümen kararı, resmî senet ve
 * mahkeme kararı. Emsal parseli kurum envanteri değildir — aynı ada/parsel
 * ikinci kez girildiğinde mevcut parsel kaydı kullanılır, yenisi açılmaz.
 */
class EmsalController extends Controller
{
    private const EVRAK_KLASOR = 'emsal/evraklar';

    /** Liste: tür sekmesi, tarih aralığı, konum ve arama; ?excel=1 aynı süzgeçle indirir. */
    public function index(Request $request): View|StreamedResponse
    {
        $f = $this->filtre($request);

        $taban = Emsal::query()
            ->ara($f['q'])
            ->tarihAraliginda([$f['baslangic'], $f['bitis']])
            ->when($f['ilce_id'], fn ($s, $v) => $s->whereIn('emsal_parsel_id', EmsalParsel::query()->select('id')->where('ilce_id', $v)))
            ->when($f['mahalle_id'], fn ($s, $v) => $s->whereIn('emsal_parsel_id', EmsalParsel::query()->select('id')->where('mahalle_id', $v)));

        $sorgu = (clone $taban)
            ->with(['parsel:id,il_id,ilce_id,mahalle_id,ada,parsel,alan,imar_durumu_id', 'parsel.ilce:id,ad', 'parsel.mahalle:id,ad', 'parsel.imarDurumu:id,ad', 'olusturan:id,ad,soyad'])
            ->tipi($f['tip'])
            ->orderByDesc('tarih')->orderByDesc('id');

        if ($request->boolean('excel')) {
            return $this->excelYaz($sorgu->get());
        }

        $sayac = ['tumu' => (clone $taban)->count()];
        foreach (EmsalTipi::cases() as $t) {
            $sayac[$t->value] = (clone $taban)->where('tip', $t->value)->count();
        }

        $suzulmus = (clone $taban)->tipi($f['tip']);
        $ozet = [
            'ortalama' => (float) (clone $suzulmus)->avg('birim_fiyat'),
            'en_yuksek' => (float) (clone $suzulmus)->max('birim_fiyat'),
            'parsel' => (clone $suzulmus)->distinct()->count('emsal_parsel_id'),
            'evraksiz' => (clone $suzulmus)->whereNull('evrak')->count(),
        ];

        return view('panel.emsal.index', [
            'kayitlar' => $sorgu->paginate(25)->withQueryString(),
            'sayac' => $sayac,
            'ozet' => $ozet,
            'filtre' => $f,
            'ilceler' => Ilce::orderBy('ad')->get(['id', 'ad']),
            'mahalleler' => $f['ilce_id'] ? Mahalle::where('ilce_id', $f['ilce_id'])->orderBy('ad')->get(['id', 'ad']) : collect(),
        ]);
    }

    public function excelIndir(Request $request): StreamedResponse
    {
        return $this->index($request->merge(['excel' => 1]));
    }

    public function olustur(Request $request): View
    {
        return view('panel.emsal.form', $this->formVerisi(null, $request));
    }

    public function kaydet(Request $request): RedirectResponse
    {
        $veri = $this->dogrula($request);
        $parsel = $this->parseliBul($veri);

        $emsal = new Emsal($this->emsalAlanlari($veri));
        $emsal->emsal_parsel_id = $parsel->id;
        $emsal->olusturan_id = $request->user()->id;
        if ($request->hasFile('evrak')) {
            $emsal->evrak = $request->file('evrak')->store(self::EVRAK_KLASOR.'/'.now()->format('Y/m'), 'public');
        }
        $emsal->save();

        $this->bagimsizBolumleriYaz($emsal, $veri['bolumler'] ?? []);

        return redirect()->route('panel.emsal.index')->with('basari', $emsal->tip->etiket().' emsali kaydedildi.');
    }

    public function duzenle(int $id): View
    {
        $emsal = Emsal::with(['parsel', 'bagimsizBolumler'])->findOrFail($id);

        return view('panel.emsal.form', $this->formVerisi($emsal, request()));
    }

    public function guncelle(Request $request, int $id): RedirectResponse
    {
        $emsal = Emsal::with('parsel')->findOrFail($id);
        $veri = $this->dogrula($request);
        $parsel = $this->parseliBul($veri);

        $eskiEvrak = $emsal->evrak;
        $eskiParselId = $emsal->emsal_parsel_id;

        $emsal->fill($this->emsalAlanlari($veri));
        $emsal->emsal_parsel_id = $parsel->id;
        if ($request->hasFile('evrak')) {
            $emsal->evrak = $request->file('evrak')->store(self::EVRAK_KLASOR.'/'.now()->format('Y/m'), 'public');
        } elseif ($request->boolean('evrak_kaldir')) {
            $emsal->evrak = null;
        }
        $emsal->save();

        if ($eskiEvrak && $emsal->evrak !== $eskiEvrak) {
            Storage::disk('public')->delete($eskiEvrak);
        }
        // Parsel değiştiyse eski parselin özeti de tazelenmeli
        if ($eskiParselId !== $parsel->id) {
            EmsalParsel::find($eskiParselId)?->ozetiTazele();
        }

        $this->bagimsizBolumleriYaz($emsal, $veri['bolumler'] ?? []);

        return redirect()->route('panel.emsal.index')->with('basari', 'Emsal güncellendi.');
    }

    public function sil(int $id): RedirectResponse
    {
        $emsal = Emsal::findOrFail($id);
        $evrak = $emsal->evrak;
        $emsal->delete();
        if ($evrak) {
            Storage::disk('public')->delete($evrak);
        }

        return redirect()->route('panel.emsal.index')->with('basari', 'Emsal silindi.');
    }

    /** @return array<string, mixed> */
    private function filtre(Request $request): array
    {
        $tipler = array_column(EmsalTipi::cases(), 'value');

        return [
            'tip' => in_array($request->query('tip'), $tipler, true) ? $request->query('tip') : null,
            'q' => trim((string) $request->query('q', '')),
            'baslangic' => $request->date('baslangic')?->format('Y-m-d'),
            'bitis' => $request->date('bitis')?->format('Y-m-d'),
            'ilce_id' => $request->query('ilce_id') ?: null,
            'mahalle_id' => $request->query('mahalle_id') ?: null,
        ];
    }

    /** @return array<string, mixed> */
    private function formVerisi(?Emsal $emsal, Request $request): array
    {
        $parsel = $emsal?->parsel;
        $ilId = old('il_id', $parsel?->il_id ?? $request->query('il_id'));
        $ilceId = old('ilce_id', $parsel?->ilce_id ?? $request->query('ilce_id'));

        return [
            'emsal' => $emsal,
            'tipler' => EmsalTipi::cases(),
            'iller' => Il::orderBy('ad')->get(['id', 'ad']),
            'ilceler' => $ilId ? Ilce::where('il_id', $ilId)->orderBy('ad')->get(['id', 'ad']) : collect(),
            'mahalleler' => $ilceId ? Mahalle::where('ilce_id', $ilceId)->orderBy('ad')->get(['id', 'ad']) : collect(),
            'imarDurumlari' => ImarDurumu::where('aktif_mi', true)->orderBy('sira')->orderBy('ad')->get(['id', 'ad']),
        ];
    }

    /** @return array<string, mixed> */
    private function dogrula(Request $request): array
    {
        return $request->validate([
            'tip' => ['required', Rule::enum(EmsalTipi::class)],
            // Parsel
            'il_id' => ['required', 'exists:iller,id'],
            'ilce_id' => ['required', 'exists:ilceler,id'],
            'mahalle_id' => ['required', 'exists:mahalleler,id'],
            'ada' => ['required', 'string', 'max:20'],
            'parsel' => ['required', 'string', 'max:20'],
            'alan' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'imar_durumu_id' => ['nullable', 'exists:imar_durumlari,id'],
            'emsal' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'yenaz_yencok' => ['nullable', 'string', 'max:50'],
            // Emsal — her türde ortak
            'tarih' => ['required', 'date', 'before_or_equal:today'],
            'birim_fiyat' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
            'evrak' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'aciklama' => ['nullable', 'string', 'max:2000'],
            // Türe özel
            'karar_no' => ['nullable', 'string', 'max:100'],
            'kayit_no' => ['nullable', 'string', 'max:100'],
            'satilan_hisse' => ['nullable', 'string', 'max:50'],
            'yevmiye_no' => ['nullable', 'string', 'max:100'],
            'satis_durumu' => ['nullable', 'string', 'max:50'],
            'esas_no' => ['nullable', 'string', 'max:100'],
            'dava_tarihi' => ['nullable', 'date', 'before_or_equal:tarih'],
            // Bağımsız bölümler
            'bolumler' => ['nullable', 'array', 'max:200'],
            'bolumler.*.bb_no' => ['nullable', 'string', 'max:30'],
            'bolumler.*.kat' => ['nullable', 'string', 'max:30'],
            'bolumler.*.cephe' => ['nullable', 'string', 'max:50'],
            'bolumler.*.brut_alan' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'bolumler.*.nitelik' => ['nullable', 'string', 'max:100'],
            'bolumler.*.bb_durumu' => ['nullable', 'string', 'max:50'],
        ]);
    }

    /**
     * Aynı ada/parsel ikinci kez girildiğinde yeni parsel açılmaz; mevcut kayıt
     * bulunur ve parsel bilgileri (alan, imar) tazelenir.
     *
     * @param  array<string, mixed>  $veri
     */
    private function parseliBul(array $veri): EmsalParsel
    {
        $parsel = EmsalParsel::firstOrNew([
            'mahalle_id' => $veri['mahalle_id'],
            'ada' => $veri['ada'],
            'parsel' => $veri['parsel'],
        ]);

        $parsel->fill([
            'il_id' => $veri['il_id'],
            'ilce_id' => $veri['ilce_id'],
            'alan' => $veri['alan'] ?? $parsel->alan,
            'imar_durumu_id' => $veri['imar_durumu_id'] ?? $parsel->imar_durumu_id,
            'emsal' => $veri['emsal'] ?? $parsel->emsal,
            'yenaz_yencok' => $veri['yenaz_yencok'] ?? $parsel->yenaz_yencok,
        ])->save();

        return $parsel;
    }

    /**
     * Emsal satırının alanları. Seçilen türe ait olmayan alanlar null yazılır,
     * böylece tür değiştirildiğinde eski türün numaraları kayıtta kalmaz.
     *
     * @param  array<string, mixed>  $veri
     * @return array<string, mixed>
     */
    private function emsalAlanlari(array $veri): array
    {
        $tip = EmsalTipi::from($veri['tip']);
        $tumTureOzel = ['karar_no', 'kayit_no', 'satilan_hisse', 'yevmiye_no', 'satis_durumu', 'esas_no', 'dava_tarihi'];

        $alanlar = [
            'tip' => $tip->value,
            'tarih' => $veri['tarih'],
            'birim_fiyat' => $veri['birim_fiyat'],
            'aciklama' => $veri['aciklama'] ?? null,
        ];
        foreach ($tumTureOzel as $alan) {
            $alanlar[$alan] = in_array($alan, $tip->alanlar(), true) ? ($veri[$alan] ?? null) : null;
        }

        return $alanlar;
    }

    /** @param  array<int, array<string, mixed>>  $bolumler */
    private function bagimsizBolumleriYaz(Emsal $emsal, array $bolumler): void
    {
        $emsal->bagimsizBolumler()->delete();

        $dolu = array_filter($bolumler, fn ($b) => collect($b)->filter(fn ($v) => filled($v))->isNotEmpty());
        if ($dolu === []) {
            return;
        }

        $emsal->bagimsizBolumler()->createMany(array_map(fn ($b) => [
            'bb_no' => $b['bb_no'] ?? null,
            'kat' => $b['kat'] ?? null,
            'cephe' => $b['cephe'] ?? null,
            'brut_alan' => $b['brut_alan'] ?? null,
            'nitelik' => $b['nitelik'] ?? null,
            'bb_durumu' => $b['bb_durumu'] ?? null,
        ], array_values($dolu)));
    }

    /** @param  Collection<int, Emsal>  $kayitlar */
    private function excelYaz(Collection $kayitlar): StreamedResponse
    {
        $ss = new Spreadsheet;
        $sh = $ss->getActiveSheet();
        $sh->setTitle('Emsaller');
        $basliklar = ['Tür', 'İlçe', 'Mahalle', 'Ada', 'Parsel', 'Parsel Alanı (m²)', 'İmar Durumu', 'Tarih', 'Birim Fiyat (TL/m²)', 'Belge No', 'Satılan Hisse', 'Açıklama', 'Evrak'];
        $sh->fromArray($basliklar, null, 'A1');
        $son = 'M';
        $sh->getStyle("A1:{$son}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0D9488']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);

        $r = 2;
        foreach ($kayitlar as $k) {
            $p = $k->parsel;
            $sh->fromArray([
                $k->tip->etiket(),
                $p?->ilce?->ad, $p?->mahalle?->ad, $p?->ada, $p?->parsel,
                $p?->alan !== null ? (float) $p->alan : null,
                $p?->imarDurumu?->ad,
                $k->tarih?->format('d.m.Y'),
                $k->birim_fiyat !== null ? (float) $k->birim_fiyat : null,
                $k->belgeNo(), $k->satilan_hisse, $k->aciklama,
                $k->evrak ? 'Var' : 'Yok',
            ], null, 'A'.$r);
            $r++;
        }
        $sh->getStyle('F2:F'.max(2, $r - 1))->getNumberFormat()->setFormatCode('#,##0.00');
        $sh->getStyle('I2:I'.max(2, $r - 1))->getNumberFormat()->setFormatCode('#,##0.00');
        foreach (range('A', $son) as $c) {
            $sh->getColumnDimension($c)->setAutoSize(true);
        }
        $sh->freezePane('A2');
        $sh->setAutoFilter("A1:{$son}".max(1, $r - 1));

        return response()->streamDownload(fn () => (new Xlsx($ss))->save('php://output'), 'emsaller-'.now()->format('Y-m-d').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
