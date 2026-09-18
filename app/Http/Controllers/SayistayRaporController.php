<?php

namespace App\Http\Controllers;

use App\Models\Ilce;
use App\Models\KayitTuru;
use App\Models\Tasinmaz;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sayıştay Raporları — 4 form:
 *  - Kayıtlı (EK-2)        : KayitTuru kod "1"  (Tapuda Kayıtlı)
 *  - Kayıtsız (EK-3)       : KayitTuru kod "2"  (Tapuda Kayıtlı Olmayan)
 *  - Orta Malları (EK-4)   : KayitTuru kod "3"
 *  - Genel Hizmet (EK-5)   : KayitTuru kod "4"
 *
 * Her rapor: aktif hisseli taşınmazları ilgili kök kategori + tüm alt
 * kategorileri altında filtreler; ilçe filtresi opsiyoneldir. Tabloda her
 * hisse için 4 satırlık Sayıştay formatı, Excel'de aynı format hücre
 * birleştirmeleriyle üretilir.
 */
class SayistayRaporController extends Controller
{
    // Root kayıt türü kodları (KayitTuruSeeder)
    private const KOD_KAYITLI = '1';
    private const KOD_KAYITSIZ = '2';
    private const KOD_ORTA_MALLARI = '3';
    private const KOD_GENEL_HIZMET = '4';

    public function kayitli(Request $request)
    {
        return $this->raporGoster($request, self::KOD_KAYITLI, [
            'baslik' => 'TAPUDA KAYITLI TAŞINMAZLAR FORMU',
            'ek' => 'EK-2',
            'view' => 'panel.sayistay-rapor.kayitli',
            'export_slug' => 'tapuda-kayitli-tasinmazlar',
        ]);
    }

    public function kayitsiz(Request $request)
    {
        return $this->raporGoster($request, self::KOD_KAYITSIZ, [
            'baslik' => 'TAPUDA KAYITLI OLMAYAN TAŞINMAZLAR FORMU',
            'ek' => 'EK-3',
            'view' => 'panel.sayistay-rapor.kayitsiz',
            'export_slug' => 'tapuda-kayitli-olmayan-tasinmazlar',
        ]);
    }

    public function ortamallari(Request $request)
    {
        return $this->raporGoster($request, self::KOD_ORTA_MALLARI, [
            'baslik' => 'ORTA MALLARI FORMU',
            'ek' => 'EK-4',
            'view' => 'panel.sayistay-rapor.orta-mallari',
            'export_slug' => 'orta-mallari',
        ]);
    }

    public function genelhizmet(Request $request)
    {
        return $this->raporGoster($request, self::KOD_GENEL_HIZMET, [
            'baslik' => 'GENEL HİZMET ALANLARI FORMU',
            'ek' => 'EK-5',
            'view' => 'panel.sayistay-rapor.genel-hizmet',
            'export_slug' => 'genel-hizmet-alanlari',
        ]);
    }

    /**
     * Ortak rapor akışı: liste ekranı + Excel export.
     */
    private function raporGoster(Request $request, string $rootKod, array $meta): View|StreamedResponse
    {
        $rootKategori = KayitTuru::query()->where('kod', $rootKod)->first();
        $kategoriIdleri = $rootKategori ? $this->tumAltKategoriIdleri($rootKategori) : [];

        $ilceler = Ilce::query()->orderBy('ad')->get(['id', 'ad']);
        $secilenIlce = $request->query('ilce_id');
        $sayfaBasi = (int) $request->query('per_page', 20);
        if (! in_array($sayfaBasi, [10, 20, 50, 100, 200], true)) {
            $sayfaBasi = 20;
        }

        $sorgu = Tasinmaz::query()
            ->mudurlukKapsami()
            ->with([
                'il:id,ad',
                'ilce:id,ad',
                'mahalle:id,ad,ilce_id',
                'tapu',
                'kategori.muhasebeKayit:id,ad,kod',
                'kategori.kayitTuru:id,ad,kod',
                'hisseler',
                'yapilar',
            ])
            ->when(! empty($kategoriIdleri), function ($q) use ($kategoriIdleri) {
                $q->whereHas('kategori', fn ($k) => $k->whereIn('kayit_turu_id', $kategoriIdleri));
            })
            ->whereHas('hisseler', fn ($h) => $h->where('hisse_durum', 'aktif'))
            ->when($secilenIlce, fn ($q) => $q->where('ilce_id', $secilenIlce))
            ->orderBy('ilce_id')
            ->orderBy('mahalle_id')
            ->orderBy('ada')
            ->orderBy('parsel');

        // Excel export — sayfalama olmadan tüm sonuçlar
        if ($request->boolean('excel')) {
            return $this->excelIndir($sorgu->get(), $meta, $secilenIlce, $ilceler);
        }

        $tasinmazlar = $sorgu->paginate($sayfaBasi)->withQueryString();
        $data = $this->veriHazirla($tasinmazlar->getCollection());

        return view($meta['view'], [
            'baslik' => $meta['baslik'],
            'ek' => $meta['ek'],
            'data' => $data,
            'tasinmazlar' => $tasinmazlar,
            'ilceler' => $ilceler,
            'secilenIlce' => $secilenIlce,
            'secilenIlceAd' => $secilenIlce ? optional($ilceler->firstWhere('id', (int) $secilenIlce))->ad : null,
            'sayfaBasi' => $sayfaBasi,
        ]);
    }

    /**
     * Kök kategoriden başlayarak tüm alt kategorilerin id'lerini toplar (recursive).
     *
     * @return array<int, int>
     */
    private function tumAltKategoriIdleri(KayitTuru $kategori): array
    {
        $ids = [$kategori->id];
        foreach ($kategori->children as $cocuk) {
            $ids = array_merge($ids, $this->tumAltKategoriIdleri($cocuk));
        }

        return $ids;
    }

    /**
     * Sayıştay tablosuna beslenecek Türkçe alan başlıklarında veri satırları.
     *
     * @return \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, array<string, mixed>>>
     */
    private function veriHazirla($tasinmazlar)
    {
        return $tasinmazlar->mapWithKeys(function (Tasinmaz $t) {
            $aktifHisseler = $t->hisseler->where('hisse_durum', 'aktif')->values();
            $ilkYapi = $t->yapilar->first();
            $kategori = $t->kategori;
            $tapu = $t->tapu;

            $satirlar = $aktifHisseler->map(function ($h) use ($t, $ilkYapi, $kategori, $tapu) {
                $pay = $h->hisse_pay !== null ? (float) $h->hisse_pay : null;
                $payda = $h->hisse_payda !== null ? (float) $h->hisse_payda : null;
                $oran = ($pay !== null && $payda !== null && $payda != 0.0)
                    ? round(($pay / $payda) * 100, 2) : null;
                $sadelesmis = $this->sadelesmisHisse($h->hisse_yuzolcum, $t->alan);
                $m2 = $h->hisse_yuzolcum !== null ? (float) $h->hisse_yuzolcum : null;

                return [
                    'Taşınmaz No' => $t->id,
                    'İlçesi' => optional($t->ilce)->ad ?? '',
                    'Mahallesi/Köyü' => optional($t->mahalle)->ad ?? '',
                    'Ada No' => $t->ada ?? '',
                    'Parsel No' => $t->parsel ?? '',
                    'Takbis Zemin No' => optional($tapu)->takbis_zemin_no ?? '',
                    'Cilt No' => optional($tapu)->cilt_no ?? '',
                    'Sayfa No' => optional($tapu)->sayfa_no ?? '',
                    'Hisse No' => $h->hisse_no ?? '-',
                    'Blok No' => optional($ilkYapi)->blok_no ?? '-',
                    'BB No' => optional($ilkYapi)->bagimsiz_bolum_no ?? '-',
                    'Alan' => $t->alan !== null ? number_format((float) $t->alan, 2, ',', '.') : '',
                    'Hisse m²' => $m2 !== null ? number_format($m2, 2, ',', '.') : '',
                    'Sadeleşmiş Hisse' => $sadelesmis,
                    'Oran' => $oran !== null ? '%'.number_format($oran, 2, ',', '.') : '',
                    'Cinsi' => $t->nitelik ?? '',
                    'Mevcut' => optional($kategori)->mevcut_kullanim_sekli ?? '',
                    'Edinme Şekli' => $h->edinme_sekli ?? '',
                    'Edinme Tarihi' => $h->edinme_tarihi ? $h->edinme_tarihi->format('d-m-Y') : '',
                    'Kayıttan Çıkış Sebebi' => $h->kayitlardan_cikis ?? '',
                    'Kayıttan Çıkış Tarihi' => $h->kayitlardan_cikistarihi
                        ? $h->kayitlardan_cikistarihi->format('d-m-Y') : '',
                    'İz Bedeli' => $this->paraFormat($h->iz_bedeli),
                    'Rayiç Bedel' => $this->paraFormat($h->rayic_bedel),
                    'Maliyet Bedeli' => $this->paraFormat($h->maliyet_bedeli),
                    'Emlak Vergi' => $this->paraFormat($h->emlak_vd),
                ];
            });

            return [$t->id => $satirlar];
        });
    }

    private function paraFormat(mixed $deger): string
    {
        if ($deger === null || $deger === '') {
            return '-';
        }

        return number_format((float) $deger, 2, ',', '.');
    }

    /**
     * pay/payda -> sadeleştirilmiş "a/b" (EBOB ile). Girdiler "12.345,67"
     * formatındaki string veya sayı olabilir.
     */
    private function sadelesmisHisse(mixed $pay, mixed $payda): string
    {
        $ebob = function ($a, $b) use (&$ebob) {
            $a = (int) abs($a);
            $b = (int) abs($b);

            return $b === 0 ? $a : $ebob($b, $a % $b);
        };

        $sayiya = fn ($v) => (int) round(((float) str_replace([',', ' '], ['.', ''], (string) $v)) * 100);
        $p = $sayiya($pay);
        $q = $sayiya($payda);
        if ($p <= 0 || $q <= 0) {
            return '-';
        }
        $g = $ebob($p, $q) ?: 1;

        return intdiv($p, $g).'/'.intdiv($q, $g);
    }

    /**
     * Excel dosyasını akış olarak indir. Sayıştay 4-satır formatı, hücre
     * birleştirmeleri ile.
     */
    private function excelIndir($tasinmazlar, array $meta, ?int $ilceId, $ilceler): StreamedResponse
    {
        $data = $this->veriHazirla($tasinmazlar);
        $ilceAd = $ilceId ? optional($ilceler->firstWhere('id', (int) $ilceId))->ad : null;
        $baslikTam = $meta['baslik'].($ilceAd ? ' - '.$ilceAd : '');

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('SAYISTAY');

        // Sayfa yapısı
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A3);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);

        $titleStyle = [
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];
        $headerStyle = [
            'font' => ['bold' => true, 'size' => 10],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E9ECEF']],
        ];
        $dataStyle = [
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'font' => ['size' => 9],
        ];

        // Başlık satırları
        $sheet->mergeCells('A1:T1');
        $sheet->setCellValue('A1', $baslikTam);
        $sheet->getStyle('A1')->applyFromArray($titleStyle);
        $sheet->getRowDimension(1)->setRowHeight(30);

        $sheet->mergeCells('A2:S2');
        $sheet->setCellValue('A2', 'ÜLKESİ / İLİ : TÜRKİYE / '.($ilceAd ? mb_strtoupper($ilceAd, 'UTF-8') : 'ANKARA'));
        $sheet->setCellValue('T2', $meta['ek']);
        $sheet->getStyle('A2:T2')->applyFromArray($headerStyle);
        $sheet->getRowDimension(2)->setRowHeight(22);

        $sheet->mergeCells('A3:T3');
        $sheet->getRowDimension(3)->setRowHeight(10);

        // Kolon başlıkları — 4 satırlı karmaşık başlık (Sayıştay formatı)
        $headerRow = 4;
        $sheet->setCellValue('A'.$headerRow, 'Sıra No');
        $sheet->setCellValue('B'.$headerRow, 'Takbis Zemin No');
        $sheet->setCellValue('C'.$headerRow, 'Taşınmaz No');
        $sheet->setCellValue('D'.$headerRow, 'İlçesi');
        $sheet->setCellValue('E'.$headerRow, 'Mahallesi/Köyü');
        $sheet->setCellValue('F'.$headerRow, 'Ada No');
        $sheet->setCellValue('G'.$headerRow, 'Parsel No');
        $sheet->setCellValue('H'.$headerRow, 'Blok No');
        $sheet->setCellValue('I'.$headerRow, 'B.Bölüm No');
        $sheet->setCellValue('J'.$headerRow, 'Cilt No');
        $sheet->setCellValue('K'.$headerRow, 'Yüzölçümü');
        $sheet->setCellValue('L'.$headerRow, 'Pay Oranı');
        $sheet->setCellValue('M'.$headerRow, 'Cinsi');
        $sheet->setCellValue('N'.$headerRow, 'Mevcut Kullanım Şekli');
        $sheet->mergeCells('O'.$headerRow.':P'.($headerRow + 1));
        $sheet->setCellValue('O'.$headerRow, 'Edinme');
        $sheet->setCellValue('Q'.$headerRow, 'Maliyet Bedeli');
        $sheet->mergeCells('R'.$headerRow.':S'.($headerRow + 1));
        $sheet->setCellValue('R'.$headerRow, 'Kayıtlardan Çıkış');
        $sheet->setCellValue('T'.$headerRow, 'Açıklamalar');

        // 2. seviye (Rayiç Bedeli)
        $sheet->setCellValue('Q'.($headerRow + 1), 'Rayiç Bedeli');

        // 3. seviye
        $sheet->setCellValue('J'.($headerRow + 2), 'Sayfa No');
        $sheet->setCellValue('O'.($headerRow + 2), 'Şekli');
        $sheet->setCellValue('P'.($headerRow + 2), 'Tarihi');
        $sheet->setCellValue('Q'.($headerRow + 2), 'İz Bedeli');
        $sheet->setCellValue('R'.($headerRow + 2), 'Nedeni');
        $sheet->setCellValue('S'.($headerRow + 2), 'Tarihi');

        // 4. seviye
        $sheet->setCellValue('J'.($headerRow + 3), 'Sıra No');
        $sheet->setCellValue('Q'.($headerRow + 3), 'Emlak V.D.');

        // Dikey birleştirmeler (4 satırlık başlık bloğu)
        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'K', 'L', 'M', 'N', 'T'] as $col) {
            $sheet->mergeCells($col.$headerRow.':'.$col.($headerRow + 3));
        }
        $sheet->mergeCells('J'.$headerRow.':J'.($headerRow + 1)); // Cilt No 2 satır
        $sheet->mergeCells('O'.($headerRow + 2).':O'.($headerRow + 3));
        $sheet->mergeCells('P'.($headerRow + 2).':P'.($headerRow + 3));
        $sheet->mergeCells('R'.($headerRow + 2).':R'.($headerRow + 3));
        $sheet->mergeCells('S'.($headerRow + 2).':S'.($headerRow + 3));

        $sheet->getStyle('A'.$headerRow.':T'.($headerRow + 3))->applyFromArray($headerStyle);
        for ($i = $headerRow; $i <= $headerRow + 3; $i++) {
            $sheet->getRowDimension($i)->setRowHeight(22);
        }

        // Data satırları
        $row = $headerRow + 4;
        $sira = 1;
        foreach ($data as $tasinmazData) {
            foreach ($tasinmazData as $hisseData) {
                $ilk = $row;
                $son = $row + 3;

                $sheet->setCellValue("A{$ilk}", $sira);
                $sheet->setCellValue("B{$ilk}", $hisseData['Takbis Zemin No']);
                $sheet->setCellValue("C{$ilk}", $hisseData['Taşınmaz No']);
                $sheet->setCellValue("D{$ilk}", $hisseData['İlçesi']);
                $sheet->setCellValue("E{$ilk}", $hisseData['Mahallesi/Köyü']);
                $sheet->setCellValue("F{$ilk}", $hisseData['Ada No']);
                $sheet->setCellValue("G{$ilk}", $hisseData['Parsel No']);
                $sheet->setCellValue("H{$ilk}", $hisseData['Blok No']);
                $sheet->setCellValue("I{$ilk}", $hisseData['BB No']);
                $sheet->setCellValue("J{$ilk}", $hisseData['Cilt No']);
                $sheet->setCellValue("J".($row + 2), $hisseData['Sayfa No']);
                $sheet->setCellValue("J".($row + 3), $hisseData['Hisse No']);
                $sheet->setCellValue("K{$ilk}", $hisseData['Alan']);
                $sheet->setCellValue("L{$ilk}", $hisseData['Sadeleşmiş Hisse']);
                $sheet->setCellValue("M{$ilk}", $hisseData['Cinsi']);
                $sheet->setCellValue("N{$ilk}", $hisseData['Mevcut']);
                $sheet->setCellValue("O{$ilk}", $hisseData['Edinme Şekli']);
                $sheet->setCellValue("P{$ilk}", $hisseData['Edinme Tarihi']);
                $sheet->setCellValue("Q{$ilk}", $hisseData['Maliyet Bedeli']);
                $sheet->setCellValue("Q".($row + 1), $hisseData['Rayiç Bedel']);
                $sheet->setCellValue("Q".($row + 2), $hisseData['İz Bedeli']);
                $sheet->setCellValue("Q".($row + 3), $hisseData['Emlak Vergi']);
                $sheet->setCellValue("R{$ilk}", $hisseData['Kayıttan Çıkış Sebebi']);
                $sheet->setCellValue("S{$ilk}", $hisseData['Kayıttan Çıkış Tarihi']);
                $sheet->setCellValue("T{$ilk}", '');

                // 4 satırlık birleşimler
                foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'K', 'L', 'M', 'N', 'O', 'P', 'R', 'S', 'T'] as $col) {
                    $sheet->mergeCells("{$col}{$ilk}:{$col}{$son}");
                }
                $sheet->mergeCells("J{$ilk}:J".($row + 1)); // Cilt No 2 satır

                $sheet->getStyle("A{$ilk}:T{$son}")->applyFromArray($dataStyle);
                for ($i = $ilk; $i <= $son; $i++) {
                    $sheet->getRowDimension($i)->setRowHeight(18);
                }

                $row += 4;
                $sira++;
            }
        }

        // Kolon genişlikleri
        $widths = [
            'A' => 6, 'B' => 15, 'C' => 10, 'D' => 14, 'E' => 18, 'F' => 8, 'G' => 8,
            'H' => 8, 'I' => 8, 'J' => 10, 'K' => 12, 'L' => 12, 'M' => 16, 'N' => 20,
            'O' => 14, 'P' => 12, 'Q' => 14, 'R' => 16, 'S' => 12, 'T' => 18,
        ];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        $slugBaslik = $meta['export_slug'];
        $slug = $slugBaslik.($ilceAd ? '-'.Str::slug($ilceAd) : '').'-'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $slug, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
