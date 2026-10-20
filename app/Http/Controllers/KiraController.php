<?php

namespace App\Http\Controllers;

use App\Models\Ilce;
use App\Models\KiraDosyasi;
use App\Models\KiraHukukiDurum;
use App\Models\KiraIsKolu;
use App\Models\KiraKiraci;
use App\Models\KiraKiracilikDurum;
use App\Models\KiraSozlesme;
use App\Models\KiraTasinmaz;
use App\Models\Mahalle;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Kira işleri sorgulama.
 *
 * Referans sistemde sorgu 17 alanlı tek bir formdu ve sonuç 4353 kaydı 100'er
 * sayfalıyordu. Süzgeçler burada ilgili tabloya iniyor: kiracı adı
 * kira_kiracilari'nda, ada/parsel kira_tasinmazlari'nda, tarih aralığı
 * kira_sozlesmeleri'nde aranıyor — dosya başına EXISTS yerine her tablo bir
 * kez süzülüp id listesi çıkarılıyor.
 */
class KiraController extends Controller
{
    /** Liste: süzgeçler, sekmeler ve özet; ?excel=1 aynı süzgeçle indirir. */
    public function index(Request $request): View|StreamedResponse
    {
        $f = $this->filtre($request);
        $taban = $this->sorgu($f);

        $sorgu = (clone $taban)
            ->with([
                'anaKiraci:id,kira_dosya_id,ad_unvan',
                'isKolu:id,ad',
                'tasinmaz:id,kira_dosya_id,ilce_id,mahalle_id,ada,parsel,adres,yuzolcum',
                'tasinmaz.ilce:id,ad',
                'tasinmaz.mahalle:id,ad',
                // latestOfMany kendi alt sorgusunu kurduğu için kolon kısıtlaması
                // yapılmaz; kısıtlanırsa kira_dosya_id belirsiz kalıyor.
                'aktifSozlesme',
            ])
            ->orderByDesc('id');

        if ($request->boolean('excel')) {
            return $this->excelYaz($sorgu->get());
        }

        return view('panel.kira.index', [
            'kayitlar' => $sorgu->paginate(50)->withQueryString(),
            'sayac' => $this->sayaclar($f),
            'ozet' => $this->ozet($taban),
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

    /**
     * Sorgu formundan gelen değerler. Hepsi isteğe bağlı; hiçbiri verilmezse
     * tüm dosyalar listelenir (referanstaki "boş sorgula" davranışı).
     *
     * @return array<string, mixed>
     */
    private function filtre(Request $request): array
    {
        $tekil = fn (string $ad, array $gecerli) => in_array($request->query($ad), $gecerli, true) ? $request->query($ad) : null;

        return [
            'q' => trim((string) $request->query('q', '')),
            'dosya_no' => trim((string) $request->query('dosya_no', '')),
            'kiraci' => trim((string) $request->query('kiraci', '')),
            'isyeri' => trim((string) $request->query('isyeri', '')),
            'ada' => trim((string) $request->query('ada', '')),
            'parsel' => trim((string) $request->query('parsel', '')),
            'dolu_bos' => $tekil('dolu_bos', ['dolu', 'bos']),
            'ihale_turu' => $tekil('ihale_turu', ['ihaleli', 'ihalesiz']),
            'is_kolu_id' => $request->query('is_kolu_id') ?: null,
            'hukuki_durum_id' => $request->query('hukuki_durum_id') ?: null,
            'kiracilik_durum_id' => $request->query('kiracilik_durum_id') ?: null,
            'ilce_id' => $request->query('ilce_id') ?: null,
            'mahalle_id' => $request->query('mahalle_id') ?: null,
            'baslangic' => $request->date('baslangic')?->format('Y-m-d'),
            'bitis' => $request->date('bitis')?->format('Y-m-d'),
            'tahliye' => $request->boolean('tahliye') ?: null,
            // Standart sorgular: ?kalan=30 / ?durum=dolmus
            'kalan' => in_array((int) $request->query('kalan'), [30, 90, 180], true) ? (int) $request->query('kalan') : null,
            'durum' => $tekil('durum', ['dolmus', 'aktif', 'pasif']),
        ];
    }

    /**
     * Süzgeçleri uygulanmış temel sorgu.
     *
     * @param  array<string, mixed>  $f
     * @return Builder<KiraDosyasi>
     */
    private function sorgu(array $f): Builder
    {
        return KiraDosyasi::query()
            ->ara($f['q'])
            ->when($f['dosya_no'] !== '', fn ($s) => $s->where(fn ($w) => $w
                ->where('dosya_no', 'like', "%{$f['dosya_no']}%")
                ->orWhere('eski_dosya_no', 'like', "%{$f['dosya_no']}%")))
            ->when($f['isyeri'] !== '', fn ($s) => $s->where('isyeri_adi', 'like', "%{$f['isyeri']}%"))
            ->when($f['dolu_bos'], fn ($s, $v) => $s->where('dolu_bos', $v))
            ->when($f['ihale_turu'], fn ($s, $v) => $s->where('ihale_turu', $v))
            ->when($f['is_kolu_id'], fn ($s, $v) => $s->where('is_kolu_id', $v))
            ->when($f['hukuki_durum_id'], fn ($s, $v) => $s->where('hukuki_durum_id', $v))
            ->when($f['kiracilik_durum_id'], fn ($s, $v) => $s->where('kiracilik_durum_id', $v))
            ->when($f['tahliye'], fn ($s) => $s->where('tahliye_ihbarnamesi_mi', true))
            ->when($f['durum'] === 'aktif', fn ($s) => $s->where('sozlesme_aktif_mi', true))
            ->when($f['durum'] === 'pasif', fn ($s) => $s->where('sozlesme_aktif_mi', false))
            // Kiracı adı / TC: kiracılar bir kez süzülür
            ->when($f['kiraci'] !== '', fn ($s) => $s->whereIn('id', KiraKiraci::query()->select('kira_dosya_id')
                ->where(fn ($w) => $w->where('ad_unvan', 'like', "%{$f['kiraci']}%")->orWhere('tc_vergi_no', $f['kiraci']))))
            // Konum ve ada/parsel: taşınmaz tablosu bir kez süzülür
            ->when($f['ada'] !== '' || $f['parsel'] !== '' || $f['ilce_id'] || $f['mahalle_id'],
                fn ($s) => $s->whereIn('id', KiraTasinmaz::query()->select('kira_dosya_id')
                    ->when($f['ada'] !== '', fn ($t) => $t->where('ada', $f['ada']))
                    ->when($f['parsel'] !== '', fn ($t) => $t->where('parsel', $f['parsel']))
                    ->when($f['ilce_id'], fn ($t, $v) => $t->where('ilce_id', $v))
                    ->when($f['mahalle_id'], fn ($t, $v) => $t->where('mahalle_id', $v))))
            // Tarih aralığı ve kalan gün: sözleşme tablosunun kapsayan indexi
            ->when($f['baslangic'] || $f['bitis'], fn ($s) => $s->whereIn('id', KiraSozlesme::query()->select('kira_dosya_id')
                ->when($f['baslangic'], fn ($x, $v) => $x->where('baslangic_tarihi', '>=', $v))
                ->when($f['bitis'], fn ($x, $v) => $x->where('bitis_tarihi', '<=', $v))))
            ->when($f['kalan'], fn ($s, $v) => $s->bitisiYaklasan($v))
            ->when($f['durum'] === 'dolmus', fn ($s) => $s->suresiDolmus());
    }

    /**
     * Sekme sayaçları. Sekmenin kendi alanı (dolu/boş) sayımdan çıkarılır ki
     * "Dolu" sekmesindeyken "Boş" sayısı da görünsün.
     *
     * @param  array<string, mixed>  $f
     * @return array<string, int>
     */
    private function sayaclar(array $f): array
    {
        $tabanDurumsuz = $this->sorgu(['dolu_bos' => null] + $f);

        return [
            'tumu' => (clone $tabanDurumsuz)->count(),
            'dolu' => (clone $tabanDurumsuz)->where('dolu_bos', 'dolu')->count(),
            'bos' => (clone $tabanDurumsuz)->where('dolu_bos', 'bos')->count(),
        ];
    }

    /**
     * @param  Builder<KiraDosyasi>  $taban
     * @return array<string, mixed>
     */
    private function ozet(Builder $taban): array
    {
        $idler = (clone $taban)->select('id');

        $bedel = KiraSozlesme::query()
            ->whereIn('kira_dosya_id', $idler)->where('aktif_mi', true)
            ->join('kira_bedelleri', 'kira_bedelleri.kira_sozlesme_id', '=', 'kira_sozlesmeleri.id')
            ->where('kira_bedelleri.donem_baslangic', '<=', now()->toDateString())
            ->where('kira_bedelleri.donem_bitis', '>=', now()->toDateString())
            ->sum('kira_bedelleri.yillik_bedel');

        return [
            'yillik_toplam' => (float) $bedel,
            'bir_ay' => (clone $taban)->bitisiYaklasan(30)->count(),
            'uc_ay' => (clone $taban)->bitisiYaklasan(90)->count(),
            'dolmus' => (clone $taban)->suresiDolmus()->count(),
        ];
    }

    /** @param  Collection<int, KiraDosyasi>  $kayitlar */
    private function excelYaz(Collection $kayitlar): StreamedResponse
    {
        $ss = new Spreadsheet;
        $sh = $ss->getActiveSheet();
        $sh->setTitle('Kira Dosyaları');
        $basliklar = ['Dosya No', 'Eski Dosya No', 'İşyeri Adı', 'Ana Kiracı', 'İş Kolu', 'İlçe', 'Mahalle', 'Ada', 'Parsel', 'Yüzölçüm (m²)', 'Durum', 'İhale', 'Kira Başlangıç', 'Kira Bitiş', 'Adres'];
        $sh->fromArray($basliklar, null, 'A1');
        $son = 'O';
        $sh->getStyle("A1:{$son}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0D9488']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);

        $r = 2;
        foreach ($kayitlar as $k) {
            $t = $k->tasinmaz;
            $s = $k->aktifSozlesme;
            $sh->fromArray([
                $k->dosya_no, $k->eski_dosya_no, $k->isyeri_adi,
                $k->anaKiraci?->ad_unvan, $k->isKolu?->ad,
                $t?->ilce?->ad, $t?->mahalle?->ad, $t?->ada, $t?->parsel,
                $t?->yuzolcum !== null ? (float) $t->yuzolcum : null,
                $k->dolu_bos->etiket(), $k->ihale_turu->etiket(),
                $s?->baslangic_tarihi?->format('d.m.Y'), $s?->bitis_tarihi?->format('d.m.Y'),
                $t?->adres,
            ], null, 'A'.$r);
            $r++;
        }
        $sh->getStyle('J2:J'.max(2, $r - 1))->getNumberFormat()->setFormatCode('#,##0.00');
        foreach (range('A', $son) as $c) {
            $sh->getColumnDimension($c)->setAutoSize(true);
        }
        $sh->freezePane('A2');
        $sh->setAutoFilter("A1:{$son}".max(1, $r - 1));

        return response()->streamDownload(fn () => (new Xlsx($ss))->save('php://output'), 'kira-dosyalari-'.now()->format('Y-m-d').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
