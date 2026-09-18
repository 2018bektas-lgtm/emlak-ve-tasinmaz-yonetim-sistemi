<?php

namespace App\Http\Controllers;

use App\Enums\SatisDurumu;
use App\Http\Controllers\Concerns\TasinmazYetkisi;
use App\Models\Ilce;
use App\Models\ImarDurumu;
use App\Models\KayitTuru;
use App\Models\Mahalle;
use App\Models\MevcutKullanimSekli;
use App\Models\MuhasebeKayit;
use App\Models\Tasinmaz;
use App\Models\TasinmazHisse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Toplu İşlemler — Excel/CSV üzerinden Taşınmaz ve Hisse toplu içe aktarım.
 *
 * Politika:
 *  - Her satır kendi transaction'ında (birinin hatası diğerlerini geriye almasın).
 *  - Aynı mahalle+ada+parsel varsa güncellenir, yoksa oluşturulur (upsert).
 *  - Hataları satır bazlı toplayıp session'a atar; kullanıcıya tam liste gösterir.
 *  - "Şablon indir" ile beklenen kolon yapısını Excel olarak sunar.
 */
class TopluIslemController extends Controller
{
    use TasinmazYetkisi;

    /** Taşınmaz Excel şablonu kolonları (sıra ile) */
    private const TASINMAZ_KOLONLAR = [
        'A' => 'İl',
        'B' => 'İlçe',
        'C' => 'Mahalle',
        'D' => 'Ada',
        'E' => 'Parsel',
        'F' => 'Alan (m²)',
        'G' => 'Nitelik',
        'H' => 'TAKBİS Zemin No',
        'I' => 'Cilt No',
        'J' => 'Sayfa No',
        'K' => 'İmar Durumu',
        'L' => 'Emsal',
        'M' => 'Yen az / Yen çok',
        'N' => 'Muhasebe Kaydı (Ad veya Kod)',
        'O' => 'Kayıt Türü (Ad veya Kod)',
        'P' => 'Mevcut Kullanım Şekli',
        'Q' => 'Satış Durumu (envanterde/hazirlik/satista/satildi/iptal)',
        'R' => 'Üzerinde Bina Var (Evet/Hayır)',
        'S' => 'Açıklama',
    ];

    /** Hisse Excel şablonu kolonları */
    private const HISSE_KOLONLAR = [
        'A' => 'Mahalle TKGM ID',
        'B' => 'Ada',
        'C' => 'Parsel',
        'D' => 'Hisse No',
        'E' => 'Pay',
        'F' => 'Payda',
        'G' => 'Edinme Şekli',
        'H' => 'Edinme Tarihi (YYYY-MM-DD)',
        'I' => 'Yevmiye No',
        'J' => 'Rayiç Bedel',
        'K' => 'Maliyet Bedeli',
        'L' => 'İz Bedeli',
        'M' => 'Emlak Vergi Değeri',
        'N' => 'Hisse Durumu (aktif/kapali)',
    ];

    public function index(): View
    {
        return view('panel.tasinmazlar.toplu-islem');
    }

    /** Taşınmaz şablonunu Excel olarak indir. */
    public function tasinmazSablon(): StreamedResponse
    {
        return $this->sablonUret('tasinmaz-sablonu', 'Taşınmaz Toplu İçe Aktarma Şablonu', self::TASINMAZ_KOLONLAR, [
            ['Ankara', 'Çankaya', 'Kızılay', '123', '45', '1250,50', 'Arsa', '10123456789', '12', '345', 'Konut', '1,50', 'Serbest', '250', '1.1.1', 'Boş Arsa', 'envanterde', 'Hayır', 'Örnek satır'],
        ]);
    }

    /** Hisse şablonunu Excel olarak indir. */
    public function hisseSablon(): StreamedResponse
    {
        return $this->sablonUret('hisse-sablonu', 'Hisse Toplu İçe Aktarma Şablonu', self::HISSE_KOLONLAR, [
            ['123456', '123', '45', '1', '1', '4', 'Satın Alma', '2024-05-12', '2024/15', '100000,00', '85000,00', '1,00', '', 'aktif'],
        ]);
    }

    /**
     * Taşınmaz Excel içe aktarım.
     */
    public function tasinmazImport(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:20480',
        ]);

        [$eklenen, $guncellenen, $hatalar] = $this->tasinmazSatirIsle($request->file('file'));

        return $this->sonucaGeri('tasinmaz', $eklenen, $guncellenen, $hatalar);
    }

    /**
     * Hisse Excel içe aktarım.
     */
    public function hisseImport(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:20480',
        ]);

        [$eklenen, $guncellenen, $hatalar] = $this->hisseSatirIsle($request->file('file'));

        return $this->sonucaGeri('hisse', $eklenen, $guncellenen, $hatalar);
    }

    // --------- Satır işleyiciler ---------

    /**
     * @return array{0:int, 1:int, 2:array<int, string>}
     */
    private function tasinmazSatirIsle($file): array
    {
        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
        // İlk satır başlık
        array_shift($rows);

        $eklenen = 0;
        $guncellenen = 0;
        $hatalar = [];

        foreach ($rows as $indeks => $satir) {
            $satirNo = $indeks; // toArray offset "A"lı; indeks Excel satır numarası
            // Tamamı boş satırı atla
            if (empty(array_filter($satir, fn ($v) => filled($v)))) {
                continue;
            }

            try {
                DB::transaction(function () use ($satir, $satirNo, &$eklenen, &$guncellenen) {
                    $ilAdi = trim((string) ($satir['A'] ?? ''));
                    $ilceAdi = trim((string) ($satir['B'] ?? ''));
                    $mahalleAdi = trim((string) ($satir['C'] ?? ''));
                    $ada = trim((string) ($satir['D'] ?? ''));
                    $parsel = trim((string) ($satir['E'] ?? ''));
                    if ($ilceAdi === '' || $mahalleAdi === '' || $ada === '' || $parsel === '') {
                        throw new \RuntimeException("Satır {$satirNo}: İlçe, Mahalle, Ada, Parsel zorunlu.");
                    }

                    $ilce = Ilce::when($ilAdi, fn ($q) => $q->whereHas('il', fn ($il) => $il->where('ad', $ilAdi)))
                        ->where('ad', $ilceAdi)->first();
                    if (! $ilce) {
                        throw new \RuntimeException("Satır {$satirNo}: İlçe bulunamadı: {$ilceAdi}");
                    }
                    $mahalle = Mahalle::where('ilce_id', $ilce->id)->where('ad', $mahalleAdi)->first();
                    if (! $mahalle) {
                        throw new \RuntimeException("Satır {$satirNo}: Mahalle bulunamadı: {$mahalleAdi}");
                    }

                    $alan = $this->trSayi($satir['F'] ?? null);
                    $nitelik = trim((string) ($satir['G'] ?? ''));
                    $binaVar = $this->evetHayir($satir['R'] ?? '');

                    $tasinmaz = Tasinmaz::firstOrNew([
                        'mahalle_id' => $mahalle->id,
                        'ada' => $ada,
                        'parsel' => $parsel,
                    ]);
                    $mevcutMuydu = $tasinmaz->exists;
                    $tasinmaz->fill([
                        'alan' => $alan,
                        'nitelik' => $nitelik ?: $tasinmaz->nitelik,
                        'uzeri_bina_var_mi' => $binaVar ?? $tasinmaz->uzeri_bina_var_mi ?? false,
                    ]);
                    // Kullanıcı müdürlük atansın
                    $veri = $this->mudurlukIdUygula(['mudurluk_id' => $tasinmaz->mudurluk_id]);
                    $tasinmaz->mudurluk_id = $veri['mudurluk_id'] ?? auth()->user()->mudurluk_id;
                    $tasinmaz->save();

                    // Tapu
                    $takbis = trim((string) ($satir['H'] ?? ''));
                    $cilt = trim((string) ($satir['I'] ?? ''));
                    $sayfa = trim((string) ($satir['J'] ?? ''));
                    if ($takbis || $cilt || $sayfa) {
                        $tapuMevcut = $tasinmaz->tapu;
                        $tapuVeri = [
                            'takbis_zemin_no' => $takbis ?: ($tapuMevcut->takbis_zemin_no ?? ''),
                            'cilt_no' => $cilt ?: ($tapuMevcut->cilt_no ?? ''),
                            'sayfa_no' => $sayfa ?: ($tapuMevcut->sayfa_no ?? ''),
                            'tapu_durumu' => $tapuMevcut->tapu_durumu ?? 'aktif',
                        ];
                        if ($tapuMevcut) {
                            $tapuMevcut->update($tapuVeri);
                        } else {
                            $tasinmaz->tapu()->create($tapuVeri);
                        }
                    }

                    // İmar
                    $imarAdi = trim((string) ($satir['K'] ?? ''));
                    if ($imarAdi !== '') {
                        $imar = ImarDurumu::firstOrCreate(['ad' => $imarAdi], ['aktif_mi' => true]);
                        $tasinmaz->imar()->updateOrCreate(
                            ['tasinmaz_id' => $tasinmaz->id],
                            [
                                'imar_durumu_id' => $imar->id,
                                'emsal' => $this->trSayi($satir['L'] ?? null),
                                'yenaz_yencok' => trim((string) ($satir['M'] ?? '')),
                            ]
                        );
                    }

                    // Kategori (muhasebe/kayıt türü/kullanım)
                    $muhasebeKey = trim((string) ($satir['N'] ?? ''));
                    $kayitTuruKey = trim((string) ($satir['O'] ?? ''));
                    $mevcutKullanim = trim((string) ($satir['P'] ?? ''));

                    $muhasebeId = $muhasebeKey !== ''
                        ? optional(MuhasebeKayit::query()
                            ->where('kod', $muhasebeKey)->orWhere('ad', $muhasebeKey)->first())->id
                        : null;
                    $kayitTuruId = $kayitTuruKey !== ''
                        ? optional(KayitTuru::query()
                            ->where('kod', $kayitTuruKey)->orWhere('ad', $kayitTuruKey)->first())->id
                        : null;

                    if ($muhasebeId || $kayitTuruId || $mevcutKullanim !== '') {
                        $tasinmaz->kategori()->updateOrCreate(
                            ['tasinmaz_id' => $tasinmaz->id],
                            [
                                'muhasebe_kayit_id' => $muhasebeId,
                                'kayit_turu_id' => $kayitTuruId,
                                'mevcut_kullanim_sekli' => $mevcutKullanim ?: null,
                            ]
                        );
                    }

                    // Ekbilgi
                    $satis = trim((string) ($satir['Q'] ?? ''));
                    $aciklama = trim((string) ($satir['S'] ?? ''));
                    $tasinmaz->ekbilgi()->updateOrCreate(
                        ['tasinmaz_id' => $tasinmaz->id],
                        [
                            'satis_durumu' => $this->satisDurumu($satis),
                            'aciklama' => $aciklama ?: null,
                        ]
                    );

                    $mevcutMuydu ? $guncellenen++ : $eklenen++;
                });
            } catch (\Throwable $e) {
                $hatalar[] = $e->getMessage();
                Log::warning('Toplu içe aktarım hatası', ['satir' => $satirNo, 'hata' => $e->getMessage()]);
            }
        }

        return [$eklenen, $guncellenen, $hatalar];
    }

    /**
     * @return array{0:int, 1:int, 2:array<int, string>}
     */
    private function hisseSatirIsle($file): array
    {
        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
        array_shift($rows);

        $eklenen = 0;
        $guncellenen = 0;
        $hatalar = [];

        foreach ($rows as $indeks => $satir) {
            $satirNo = $indeks;
            if (empty(array_filter($satir, fn ($v) => filled($v)))) {
                continue;
            }

            try {
                DB::transaction(function () use ($satir, $satirNo, &$eklenen, &$guncellenen) {
                    $mahalleTkgm = (int) ($satir['A'] ?? 0);
                    $ada = trim((string) ($satir['B'] ?? ''));
                    $parsel = trim((string) ($satir['C'] ?? ''));
                    if (! $mahalleTkgm || $ada === '' || $parsel === '') {
                        throw new \RuntimeException("Satır {$satirNo}: Mahalle TKGM ID, Ada, Parsel zorunlu.");
                    }

                    $tasinmaz = Tasinmaz::query()
                        ->whereHas('mahalle', fn ($q) => $q->where('tkgm_id', $mahalleTkgm))
                        ->where('ada', $ada)->where('parsel', $parsel)
                        ->first();
                    if (! $tasinmaz) {
                        throw new \RuntimeException("Satır {$satirNo}: Taşınmaz bulunamadı: TKGM {$mahalleTkgm} · {$ada}/{$parsel}");
                    }
                    if (! $tasinmaz->mudurlukErisilebilirMi()) {
                        throw new \RuntimeException("Satır {$satirNo}: Bu taşınmaza erişim yetkiniz yok.");
                    }

                    $hisseNo = trim((string) ($satir['D'] ?? ''));
                    $pay = $this->trSayi($satir['E'] ?? null);
                    $payda = $this->trSayi($satir['F'] ?? null);
                    if ($pay === null || $payda === null || (float) $payda === 0.0) {
                        throw new \RuntimeException("Satır {$satirNo}: Pay/Payda geçerli değil.");
                    }

                    $durum = trim((string) ($satir['N'] ?? '')) ?: 'aktif';
                    $anahtar = [
                        'tasinmaz_id' => $tasinmaz->id,
                        'hisse_no' => $hisseNo ?: null,
                    ];
                    $mevcut = TasinmazHisse::where($anahtar)->first();
                    $veri = [
                        'hisse_pay' => $pay,
                        'hisse_payda' => $payda,
                        'edinme_sekli' => trim((string) ($satir['G'] ?? '')) ?: null,
                        'edinme_tarihi' => $this->tarih($satir['H'] ?? null),
                        'yevmiye_no' => trim((string) ($satir['I'] ?? '')) ?: null,
                        'rayic_bedel' => $this->trSayi($satir['J'] ?? null),
                        'maliyet_bedeli' => $this->trSayi($satir['K'] ?? null),
                        'iz_bedeli' => $this->trSayi($satir['L'] ?? null),
                        'emlak_vd' => $this->trSayi($satir['M'] ?? null),
                        'hisse_durum' => $durum,
                    ];

                    if ($mevcut) {
                        $mevcut->update($veri);
                        $guncellenen++;
                    } else {
                        $tasinmaz->hisseler()->create(array_merge($anahtar, $veri));
                        $eklenen++;
                    }
                });
            } catch (\Throwable $e) {
                $hatalar[] = $e->getMessage();
            }
        }

        return [$eklenen, $guncellenen, $hatalar];
    }

    // --------- Yardımcılar ---------

    private function trSayi(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return (string) $v;
        }
        $s = preg_replace('/[\s\x{00A0}]/u', '', (string) $v) ?? '';
        if (preg_match('/^(.+)[.,](\d{1,4})$/', $s, $m)) {
            $s = str_replace(['.', ','], '', $m[1]).'.'.$m[2];
        } else {
            $s = str_replace(['.', ','], '', $s);
        }

        return is_numeric($s) ? $s : null;
    }

    private function evetHayir(mixed $v): ?bool
    {
        $s = mb_strtolower(trim((string) $v), 'UTF-8');
        if ($s === '') {
            return null;
        }
        if (in_array($s, ['evet', 'e', 'var', '1', 'true', 'yes'], true)) {
            return true;
        }
        if (in_array($s, ['hayır', 'hayir', 'h', 'yok', '0', 'false', 'no'], true)) {
            return false;
        }

        return null;
    }

    private function satisDurumu(mixed $v): string
    {
        $s = mb_strtolower(trim((string) $v), 'UTF-8');

        return SatisDurumu::tryFrom($s)->value ?? SatisDurumu::Envanterde->value;
    }

    private function tarih(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        // Excel serial (float)
        if (is_numeric($v) && $v > 20000 && $v < 80000) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $v)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }
        try {
            return \Carbon\Carbon::parse((string) $v)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function sonucaGeri(string $tip, int $eklenen, int $guncellenen, array $hatalar): RedirectResponse
    {
        $mesaj = "{$tip}: {$eklenen} yeni, {$guncellenen} güncellendi";
        if (! empty($hatalar)) {
            $mesaj .= ', '.count($hatalar).' hata';
            session()->flash('import_hatalari', $hatalar);
        }

        return redirect()->route('panel.tasinmazlar.toplu-islem')->with('basari', $mesaj);
    }

    private function sablonUret(string $slug, string $baslik, array $kolonlar, array $ornekSatirlar): StreamedResponse
    {
        $ss = new Spreadsheet;
        $sh = $ss->getActiveSheet();
        $sh->setTitle('Şablon');

        // Başlık
        $sonKolon = array_key_last($kolonlar);
        $sh->mergeCells('A1:'.$sonKolon.'1');
        $sh->setCellValue('A1', $baslik);
        $sh->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0E1726']],
        ]);
        $sh->getStyle('A1')->getFont()->getColor()->setRGB('D8BC8C');
        $sh->getRowDimension(1)->setRowHeight(30);

        // Kolon başlıkları
        foreach ($kolonlar as $harf => $ad) {
            $sh->setCellValue($harf.'2', $ad);
            $sh->getColumnDimension($harf)->setWidth(22);
        }
        $sh->getStyle('A2:'.$sonKolon.'2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E9ECEF']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
        $sh->getRowDimension(2)->setRowHeight(38);

        // Örnek satırlar
        $row = 3;
        foreach ($ornekSatirlar as $ornek) {
            $col = 'A';
            foreach ($ornek as $deger) {
                $sh->setCellValue($col.$row, $deger);
                $col++;
            }
            $sh->getStyle('A'.$row.':'.$sonKolon.$row)->applyFromArray([
                'font' => ['italic' => true, 'color' => ['rgb' => '6c7484']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DBE0E8']]],
            ]);
            $row++;
        }

        $filename = $slug.'-'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($ss) {
            (new Xlsx($ss))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
