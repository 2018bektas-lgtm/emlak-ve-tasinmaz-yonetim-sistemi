<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\TasinmazYetkisi;
use App\Http\Requests\StoreTasinmazHisseRequest;
use App\Models\Il;
use App\Models\Ilce;
use App\Models\Mahalle;
use App\Models\TasinmazHisse;
use App\Support\HisseKolonlari;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TasinmazHisseController extends Controller
{
    use TasinmazYetkisi;

    public function index(Request $request): View
    {
        $filtre = $this->filtreDegerleri($request);
        $sorgu = $this->listeSorgusu($request);

        $durumOzeti = $this->durumOzeti($request);
        $durumSayilari = [
            'aktif' => $durumOzeti['aktif']['adet'],
            'pasif' => $durumOzeti['pasif']['adet'],
        ];
        $durumM2 = [
            'aktif' => $durumOzeti['aktif']['m2'],
            'pasif' => $durumOzeti['pasif']['m2'],
        ];

        $hisseler = $sorgu->paginate(20)->withQueryString();

        return view('panel.tasinmazlar.hisseler', [
            'hisseler' => $hisseler,
            'sonucM2' => $this->m2Toplami($this->listeSorgusu($request)),
            'sayfaM2' => round((float) $hisseler->getCollection()->sum(fn (TasinmazHisse $h) => $h->hesaplananYuzolcum() ?? 0), 2),
            'toplamM2' => $durumM2['aktif'] + $durumM2['pasif'],
            'durumM2' => $durumM2,
            'iller' => Il::orderBy('ad')->get(['id', 'ad']),
            'ilceler' => $filtre['il_id']
                ? Ilce::where('il_id', $filtre['il_id'])->orderBy('ad')->get(['id', 'ad'])
                : collect(),
            'mahalleler' => $filtre['ilce_id']
                ? Mahalle::where('ilce_id', $filtre['ilce_id'])->orderBy('ad')->get(['id', 'ad'])
                : collect(),
            'filtre' => $filtre,
            'toplamKayit' => array_sum($durumSayilari),
            'durumSayilari' => $durumSayilari,
            'gorunurKolonlar' => HisseKolonlari::kullaniciIcin($request->user()),
            'tumKolonlar' => HisseKolonlari::katalog(),
            'kolonGruplari' => HisseKolonlari::gruplar(),
        ]);
    }

    public function excelIndir(Request $request): StreamedResponse
    {
        $kayitlar = $this->listeSorgusu($request)->get();
        $sonucM2 = round((float) $kayitlar->sum(fn (TasinmazHisse $h) => $h->hesaplananYuzolcum() ?? 0), 2);
        $secilen = $this->secilenBasliklar($request);

        $ss = new Spreadsheet;
        $sh = $ss->getActiveSheet();
        $sh->setTitle('Hisseler');

        $kolonlar = ['#' => 'Sıra'] + $secilen;
        $sonHarf = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($kolonlar));

        $baslikStil = [
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'D8BC8C']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0E1726']],
        ];
        $ustStil = [
            'font' => ['bold' => true, 'size' => 10],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E9ECEF']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];
        $veriStil = [
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DBE0E8']]],
            'font' => ['size' => 9],
        ];

        $sh->mergeCells('A1:'.$sonHarf.'1');
        $sh->setCellValue('A1', 'HİSSE LİSTESİ · '.now()->format('d.m.Y H:i').' · '.$kayitlar->count().' kayıt · '.number_format($sonucM2, 2, ',', '.').' m²');
        $sh->getStyle('A1')->applyFromArray($baslikStil);
        $sh->getRowDimension(1)->setRowHeight(30);

        $i = 1;
        foreach ($kolonlar as $etiket) {
            $sh->setCellValue([$i, 2], $etiket);
            $i++;
        }
        $sh->getStyle('A2:'.$sonHarf.'2')->applyFromArray($ustStil);

        $satir = 3;
        foreach ($kayitlar as $sira => $h) {
            $sh->setCellValue([1, $satir], $sira + 1);
            $kolon = 2;
            foreach (array_keys($secilen) as $anahtar) {
                $sh->setCellValue([$kolon, $satir], $this->kolonDegeri($h, $anahtar));
                $kolon++;
            }
            $satir++;
        }

        if ($kayitlar->isNotEmpty()) {
            $sh->getStyle('A3:'.$sonHarf.($satir - 1))->applyFromArray($veriStil);
        }

        $sh->setCellValue('A'.$satir, 'Toplam hisse m²');
        $m2Sira = array_search('hisse_m2', array_keys($secilen), true);
        if ($m2Sira !== false) {
            $sh->setCellValue([$m2Sira + 2, $satir], $sonucM2);
        }
        $sh->getStyle('A'.$satir.':'.$sonHarf.$satir)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF4E8']],
        ]);

        foreach (array_keys($kolonlar) as $siraNo => $anahtar) {
            $harf = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($siraNo + 1);
            $sh->getColumnDimension($harf)->setWidth($this->kolonGenisligi($anahtar));
        }

        $dosya = 'hisseler-'.now()->format('Y-m-d-Hi').'.xlsx';

        return response()->streamDownload(function () use ($ss) {
            (new Xlsx($ss))->save('php://output');
        }, $dosya, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function pdfIndir(Request $request): View
    {
        $kayitlar = $this->listeSorgusu($request)->get();

        return view('panel.tasinmazlar.hisseler-pdf', [
            'hisseler' => $kayitlar,
            'olusturma' => now(),
            'basliklar' => $this->secilenBasliklar($request),
            'sonucM2' => round((float) $kayitlar->sum(fn (TasinmazHisse $h) => $h->hesaplananYuzolcum() ?? 0), 2),
            'kolonUret' => fn (TasinmazHisse $h, string $anahtar) => $this->kolonMetni($h, $anahtar),
        ]);
    }

    public function kmlIndir(Request $request): Response
    {
        $kayitlar = $this->listeSorgusu($request)->get();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<kml xmlns="http://www.opengis.net/kml/2.2">'."\n"
            .'<Document>'."\n"
            .'<name>hisseler_'.now()->format('Y-m-d_H-i-s').'.kml</name>'."\n"
            .'<Style id="polygonStyle"><LineStyle><color>ff0e1726</color><width>2.5</width></LineStyle>'
            .'<PolyStyle><color>7fd8bc8c</color></PolyStyle></Style>'."\n"
            .'<Style id="pointStyle"><IconStyle><scale>1.1</scale>'
            .'<Icon><href>http://maps.google.com/mapfiles/kml/pushpin/ylw-pushpin.png</href></Icon>'
            .'</IconStyle></Style>'."\n";

        $gorulen = [];
        foreach ($kayitlar as $h) {
            $t = $h->tasinmaz;
            $k = $t?->koordinat;
            if (! $t || ! $k || isset($gorulen[$t->id])) {
                continue;
            }
            $gorulen[$t->id] = true;

            $desc = htmlspecialchars(
                'Nitelik: '.($t->nitelik ?? '—').PHP_EOL
                .'İlçe: '.(optional($t->ilce)->ad ?? '—').PHP_EOL
                .'Mahalle: '.(optional($t->mahalle)->ad ?? '—').PHP_EOL
                .'Ada: '.($t->ada ?? '—').' · Parsel: '.($t->parsel ?? '—'),
                ENT_XML1 | ENT_QUOTES,
                'UTF-8'
            );
            $ad = htmlspecialchars('Ada '.($t->ada ?? '—').' / Parsel '.($t->parsel ?? '—'), ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $geom = $k->koordinat;
            if (is_string($geom)) {
                $geom = json_decode($geom, true);
            }

            $halka = null;
            if (is_array($geom) && isset($geom['type']) && $geom['type'] === 'Polygon' && isset($geom['coordinates'][0])) {
                $halka = $geom['coordinates'][0];
            } elseif (is_array($geom) && isset($geom[0][0]) && is_numeric($geom[0][0])) {
                $halka = $geom;
            }

            if (is_array($halka) && count($halka) >= 3) {
                $koordStr = [];
                foreach ($halka as $c) {
                    if (is_array($c) && count($c) >= 2) {
                        $koordStr[] = $c[0].','.$c[1].',0';
                    }
                }
                if ($koordStr !== [] && $koordStr[0] !== end($koordStr)) {
                    $koordStr[] = $koordStr[0];
                }
                $xml .= '<Placemark><name>'.$ad.'</name>'
                    .'<description><![CDATA['.$desc.']]></description>'
                    .'<styleUrl>#polygonStyle</styleUrl>'
                    .'<Polygon><outerBoundaryIs><LinearRing><coordinates>'
                    .implode(' ', $koordStr)
                    .'</coordinates></LinearRing></outerBoundaryIs></Polygon>'
                    .'</Placemark>'."\n";
            } elseif ($k->lat !== null && $k->lng !== null) {
                $xml .= '<Placemark><name>'.$ad.'</name>'
                    .'<description><![CDATA['.$desc.']]></description>'
                    .'<styleUrl>#pointStyle</styleUrl>'
                    .'<Point><coordinates>'.$k->lng.','.$k->lat.',0</coordinates></Point>'
                    .'</Placemark>'."\n";
            }
        }

        $xml .= '</Document></kml>';
        $dosya = 'hisseler-'.now()->format('Y-m-d-Hi').'.kml';

        return response($xml, 200, [
            'Content-Type' => 'application/vnd.google-earth.kml+xml',
            'Content-Disposition' => 'attachment; filename="'.$dosya.'"',
        ]);
    }

    /** @return array<string, string> */
    private function secilenBasliklar(Request $request): array
    {
        $katalog = HisseKolonlari::katalog();
        $basliklar = [];
        foreach (HisseKolonlari::kullaniciIcin($request->user()) as $anahtar) {
            if (isset($katalog[$anahtar])) {
                $basliklar[$anahtar] = $katalog[$anahtar];
            }
        }

        return $basliklar;
    }

    private function kolonDegeri(TasinmazHisse $h, string $anahtar): mixed
    {
        $t = $h->tasinmaz;
        $pay = $h->hisse_pay !== null ? (float) $h->hisse_pay : null;
        $payda = $h->hisse_payda !== null ? (float) $h->hisse_payda : null;
        $para = function (string $alan) use ($h): ?float {
            return $h->{$alan} !== null ? (float) $h->{$alan} : null;
        };

        return match ($anahtar) {
            'il' => $t?->il?->ad ?? '',
            'ilce' => $t?->ilce?->ad ?? '',
            'mahalle' => $t?->mahalle?->ad ?? '',
            'ada' => $t?->ada ?? '',
            'parsel' => $t?->parsel ?? '',
            'nitelik' => $t?->nitelik ?? '',
            'hisse_no' => $h->hisse_no ?? '',
            'pay_payda' => ($pay !== null || $payda !== null)
                ? $this->kesirMetni($pay).' / '.$this->kesirMetni($payda)
                : '',
            'oran' => ($pay !== null && $payda) ? round(($pay / $payda) * 100, 2) : null,
            'hisse_m2' => $h->hesaplananYuzolcum(),
            'parsel_m2' => $t && $t->alan !== null ? (float) $t->alan : null,
            'hisse_adedi' => $t ? (int) ($t->hisseler_count ?? 0) : null,
            'aktif_hisse' => $t ? (int) ($t->aktif_hisse_adedi ?? 0) : null,
            'edinme' => $h->edinme_sekli ?? '',
            'tarih' => $h->edinme_tarihi?->format('d.m.Y') ?? '',
            'yevmiye' => $h->yevmiye_no ?? '',
            'islem' => match ($h->islem_tipi) {
                'alis' => 'Alış',
                'satis' => 'Satış',
                default => '',
            },
            'durum' => $h->hisse_durum === 'aktif' ? 'Aktif' : 'Pasif',
            'rayic' => $para('rayic_bedel'),
            'maliyet' => $para('maliyet_bedeli'),
            'emlak_vd' => $para('emlak_vd'),
            'iz_bedeli' => $para('iz_bedeli'),
            'cikis' => $h->kayitlardan_cikis ?? '',
            'cikis_tarihi' => $h->kayitlardan_cikistarihi?->format('d.m.Y') ?? '',
            default => '',
        };
    }

    private function kolonMetni(TasinmazHisse $h, string $anahtar): string
    {
        $deger = $this->kolonDegeri($h, $anahtar);
        if ($deger === null || $deger === '') {
            return '';
        }
        if (in_array($anahtar, ['hisse_m2', 'parsel_m2'], true)) {
            return number_format((float) $deger, 2, ',', '.').' m²';
        }
        if ($anahtar === 'oran') {
            return '%'.number_format((float) $deger, 2, ',', '.');
        }
        if (in_array($anahtar, ['rayic', 'maliyet', 'emlak_vd', 'iz_bedeli'], true)) {
            return number_format((float) $deger, 2, ',', '.').' ₺';
        }

        return (string) $deger;
    }

    private function kolonGenisligi(string $anahtar): int
    {
        return match ($anahtar) {
            '#' => 6,
            'ada', 'parsel', 'durum', 'islem' => 12,
            'hisse_no', 'oran', 'yevmiye', 'tarih', 'cikis_tarihi' => 14,
            'il', 'ilce', 'mahalle', 'hisse_m2', 'parsel_m2', 'rayic', 'maliyet', 'emlak_vd', 'iz_bedeli' => 16,
            'nitelik', 'edinme', 'pay_payda', 'cikis' => 22,
            default => 14,
        };
    }

    private function kesirMetni(?float $deger): string
    {
        if ($deger === null) {
            return '—';
        }
        $metin = number_format($deger, 4, ',', '.');

        return rtrim(rtrim($metin, '0'), ',') ?: '0';
    }

    /** @return array<string, mixed> */
    private function filtreDegerleri(Request $request): array
    {
        $durum = $request->query('durum');
        $islem = $request->query('islem');
        $sayi = fn (string $anahtar) => $this->sayiMetni($request->query($anahtar));
        $metin = fn (string $anahtar, int $limit = 150) => $this->metinMetni($request->query($anahtar), $limit);
        $tarih = fn (string $anahtar) => $this->tarihMetni($request->query($anahtar));

        return [
            'q' => trim((string) $request->query('q', '')),
            'ada' => trim((string) $request->query('ada', '')),
            'parsel' => trim((string) $request->query('parsel', '')),
            'il_id' => $request->query('il_id'),
            'ilce_id' => $request->query('ilce_id'),
            'mahalle_id' => $request->query('mahalle_id'),
            'durum' => is_string($durum) && in_array($durum, ['aktif', 'pasif'], true) ? $durum : null,
            'islem' => is_string($islem) && in_array($islem, ['alis', 'satis'], true) ? $islem : null,
            'nitelik' => $metin('nitelik'),
            'hisse_no' => $metin('hisse_no', 20),
            'edinme' => $metin('edinme', 100),
            'yevmiye' => $metin('yevmiye', 20),
            'cikis' => $metin('cikis'),
            'hisse_min' => $sayi('hisse_min'),
            'hisse_max' => $sayi('hisse_max'),
            'alan_min' => $sayi('alan_min'),
            'alan_max' => $sayi('alan_max'),
            'rayic_min' => $sayi('rayic_min'),
            'rayic_max' => $sayi('rayic_max'),
            'oran_min' => $sayi('oran_min'),
            'oran_max' => $sayi('oran_max'),
            'pay_min' => $sayi('pay_min'),
            'pay_max' => $sayi('pay_max'),
            'payda_min' => $sayi('payda_min'),
            'payda_max' => $sayi('payda_max'),
            'maliyet_min' => $sayi('maliyet_min'),
            'maliyet_max' => $sayi('maliyet_max'),
            'emlak_min' => $sayi('emlak_min'),
            'emlak_max' => $sayi('emlak_max'),
            'iz_min' => $sayi('iz_min'),
            'iz_max' => $sayi('iz_max'),
            'adet_min' => $sayi('adet_min'),
            'adet_max' => $sayi('adet_max'),
            'aktif_min' => $sayi('aktif_min'),
            'aktif_max' => $sayi('aktif_max'),
            'tarih_min' => $tarih('tarih_min'),
            'tarih_max' => $tarih('tarih_max'),
            'cikis_tarih_min' => $tarih('cikis_tarih_min'),
            'cikis_tarih_max' => $tarih('cikis_tarih_max'),
        ];
    }

    private function filtreUygula(Builder $sorgu, Request $request, bool $durumDahil = true): Builder
    {
        $f = $this->filtreDegerleri($request);
        $sayi = fn (string $anahtar): ?float => $this->sayiAyikla($f[$anahtar]);
        $alanMin = $sayi('alan_min');
        $alanMax = $sayi('alan_max');

        $sorgu
            ->when($durumDahil && $f['durum'], fn (Builder $s) => $s->where('hisse_durum', $f['durum']))
            ->when($f['islem'], fn (Builder $s) => $s->where('islem_tipi', $f['islem']))
            ->when($f['ada'] !== '', fn (Builder $s) => $s->whereHas('tasinmaz', fn (Builder $t) => $t->where('ada', $f['ada'])))
            ->when($f['parsel'] !== '', fn (Builder $s) => $s->whereHas('tasinmaz', fn (Builder $t) => $t->where('parsel', $f['parsel'])))
            ->when($f['il_id'], fn (Builder $s) => $s->whereHas('tasinmaz', fn (Builder $t) => $t->where('il_id', $f['il_id'])))
            ->when($f['ilce_id'], fn (Builder $s) => $s->whereHas('tasinmaz', fn (Builder $t) => $t->where('ilce_id', $f['ilce_id'])))
            ->when($f['mahalle_id'], fn (Builder $s) => $s->whereHas('tasinmaz', fn (Builder $t) => $t->where('mahalle_id', $f['mahalle_id'])))
            ->when($f['nitelik'] !== '', fn (Builder $s) => $s->whereHas('tasinmaz', fn (Builder $t) => $t->where('nitelik', 'like', '%'.$f['nitelik'].'%')))
            ->when($f['hisse_no'] !== '', fn (Builder $s) => $s->where('hisse_no', 'like', '%'.$f['hisse_no'].'%'))
            ->when($f['edinme'] !== '', fn (Builder $s) => $s->where('edinme_sekli', 'like', '%'.$f['edinme'].'%'))
            ->when($f['yevmiye'] !== '', fn (Builder $s) => $s->where('yevmiye_no', 'like', '%'.$f['yevmiye'].'%'))
            ->when($f['cikis'] !== '', fn (Builder $s) => $s->where('kayitlardan_cikis', 'like', '%'.$f['cikis'].'%'))
            ->when($alanMin !== null || $alanMax !== null, function (Builder $s) use ($alanMin, $alanMax) {
                $s->whereHas('tasinmaz', function (Builder $t) use ($alanMin, $alanMax) {
                    $t->when($alanMin !== null, fn (Builder $q) => $q->where('alan', '>=', $alanMin))
                        ->when($alanMax !== null, fn (Builder $q) => $q->where('alan', '<=', $alanMax));
                });
            })
            ->when($f['q'] !== '', function (Builder $s) use ($f) {
                $q = $f['q'];
                $s->where(function (Builder $w) use ($q) {
                    $w->where('edinme_sekli', 'like', "%{$q}%")
                        ->orWhere('hisse_no', 'like', "%{$q}%")
                        ->orWhere('yevmiye_no', 'like', "%{$q}%")
                        ->orWhereHas('tasinmaz', function (Builder $t) use ($q) {
                            $t->where('ada', 'like', "%{$q}%")
                                ->orWhere('parsel', 'like', "%{$q}%")
                                ->orWhere('nitelik', 'like', "%{$q}%")
                                ->orWhereHas('mahalle', fn (Builder $m) => $m->where('ad', 'like', "%{$q}%"))
                                ->orWhereHas('ilce', fn (Builder $i) => $i->where('ad', 'like', "%{$q}%"));
                        });
                });
            });

        $this->kolonAraligi($sorgu, 'hisse_pay', $sayi('pay_min'), $sayi('pay_max'));
        $this->kolonAraligi($sorgu, 'hisse_payda', $sayi('payda_min'), $sayi('payda_max'));
        $this->kolonAraligi($sorgu, 'rayic_bedel', $sayi('rayic_min'), $sayi('rayic_max'));
        $this->kolonAraligi($sorgu, 'maliyet_bedeli', $sayi('maliyet_min'), $sayi('maliyet_max'));
        $this->kolonAraligi($sorgu, 'emlak_vd', $sayi('emlak_min'), $sayi('emlak_max'));
        $this->kolonAraligi($sorgu, 'iz_bedeli', $sayi('iz_min'), $sayi('iz_max'));
        $this->ifadeAraligi($sorgu, $this->m2Ifadesi(), $sayi('hisse_min'), $sayi('hisse_max'));
        $this->ifadeAraligi($sorgu, $this->oranIfadesi(), $sayi('oran_min'), $sayi('oran_max'));
        $this->ifadeAraligi($sorgu, $this->hisseAdetIfadesi(false), $sayi('adet_min'), $sayi('adet_max'));
        $this->ifadeAraligi($sorgu, $this->hisseAdetIfadesi(true), $sayi('aktif_min'), $sayi('aktif_max'));
        $this->tarihAraligi($sorgu, 'edinme_tarihi', $f['tarih_min'], $f['tarih_max']);
        $this->tarihAraligi($sorgu, 'kayitlardan_cikistarihi', $f['cikis_tarih_min'], $f['cikis_tarih_max']);

        return $sorgu;
    }

    private function listeSorgusu(Request $request): Builder
    {
        $sorgu = TasinmazHisse::query();

        if ($request->filled('ids')) {
            $idler = collect(explode(',', (string) $request->query('ids')))
                ->map(fn ($v) => (int) trim($v))
                ->filter(fn ($v) => $v > 0)
                ->unique()
                ->values()
                ->all();
            $sorgu->whereIn('tasinmaz_hisseler.id', $idler);
        } else {
            $this->filtreUygula($sorgu, $request);
        }

        return $sorgu->with([
            'tasinmaz' => function ($iliski) {
                $iliski->select([
                    'id', 'il_id', 'ilce_id', 'mahalle_id', 'ada', 'parsel', 'alan', 'nitelik', 'kayit_tipi',
                ])->withCount([
                    'hisseler',
                    'hisseler as aktif_hisse_adedi' => fn (Builder $s) => $s->where('hisse_durum', 'aktif'),
                ]);
            },
            'tasinmaz.il:id,ad',
            'tasinmaz.ilce:id,ad',
            'tasinmaz.mahalle:id,ad,tkgm_id',
            'tasinmaz.koordinat',
            'tasinmaz.yapilar:id,tasinmaz_id,blok_no,bagimsiz_bolum_no',
        ])->orderByDesc('tasinmaz_hisseler.id');
    }

    /** @return array<string, array{adet: int, m2: float}> */
    private function durumOzeti(Request $request): array
    {
        $satirlar = $this->filtreUygula(TasinmazHisse::query(), $request, false)
            ->leftJoin('tasinmazlar as t_alan', 't_alan.id', '=', 'tasinmaz_hisseler.tasinmaz_id')
            ->select('tasinmaz_hisseler.hisse_durum as durum')
            ->selectRaw('COUNT(tasinmaz_hisseler.id) as adet')
            ->selectRaw('COALESCE(SUM('.$this->m2JoinIfadesi().'), 0) as m2')
            ->groupBy('tasinmaz_hisseler.hisse_durum')
            ->get();

        $ozet = [
            'aktif' => ['adet' => 0, 'm2' => 0.0],
            'pasif' => ['adet' => 0, 'm2' => 0.0],
        ];
        foreach ($satirlar as $satir) {
            $durum = (string) $satir->durum;
            if (! isset($ozet[$durum])) {
                continue;
            }
            $ozet[$durum] = [
                'adet' => (int) $satir->adet,
                'm2' => round((float) $satir->m2, 2),
            ];
        }

        return $ozet;
    }

    /**
     * Aralık filtresi GROUP BY kullanmaz; parsel alanı alt sorgu ile okunur.
     */
    private function m2Ifadesi(): string
    {
        return $this->m2Formulu('(SELECT alan FROM tasinmazlar WHERE tasinmazlar.id = tasinmaz_hisseler.tasinmaz_id)');
    }

    /**
     * Toplamlarda alt sorgu ONLY_FULL_GROUP_BY altında tasinmaz_id istiyor.
     * Hisse başına tek parsel olduğundan t_alan join'i satır çoğaltmaz.
     */
    private function m2JoinIfadesi(): string
    {
        return $this->m2Formulu('t_alan.alan');
    }

    private function m2Formulu(string $alanIfadesi): string
    {
        return 'COALESCE(tasinmaz_hisseler.hisse_yuzolcum, CASE WHEN tasinmaz_hisseler.hisse_pay IS NOT NULL AND tasinmaz_hisseler.hisse_payda IS NOT NULL AND tasinmaz_hisseler.hisse_payda <> 0 AND '.$alanIfadesi.' IS NOT NULL THEN ROUND((tasinmaz_hisseler.hisse_pay / tasinmaz_hisseler.hisse_payda) * '.$alanIfadesi.', 2) ELSE 0 END)';
    }

    private function oranIfadesi(): string
    {
        return '(CASE WHEN tasinmaz_hisseler.hisse_pay IS NOT NULL AND tasinmaz_hisseler.hisse_payda IS NOT NULL AND tasinmaz_hisseler.hisse_payda <> 0 THEN (tasinmaz_hisseler.hisse_pay / tasinmaz_hisseler.hisse_payda) * 100 ELSE NULL END)';
    }

    private function m2Toplami(Builder $sorgu): float
    {
        $kopya = (clone $sorgu)->setEagerLoads([])->reorder();
        $kopya->getQuery()->columns = null;
        $satir = $kopya
            ->leftJoin('tasinmazlar as t_alan', 't_alan.id', '=', 'tasinmaz_hisseler.tasinmaz_id')
            ->selectRaw('COALESCE(SUM('.$this->m2JoinIfadesi().'), 0) as m2')
            ->toBase()
            ->first();

        return round((float) ($satir->m2 ?? 0), 2);
    }

    private function sayiMetni(mixed $deger): string
    {
        $ham = trim((string) ($deger ?? ''));
        if ($ham === '' || $this->sayiAyikla($ham) === null) {
            return '';
        }

        return $ham;
    }

    private function sayiAyikla(mixed $deger): ?float
    {
        if ($deger === null) {
            return null;
        }
        $metin = trim((string) $deger);
        if ($metin === '') {
            return null;
        }
        $metin = str_replace([' ', "\u{00A0}"], '', $metin);
        if (str_contains($metin, ',')) {
            $metin = str_replace('.', '', $metin);
            $metin = str_replace(',', '.', $metin);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $metin)) {
            $metin = str_replace('.', '', $metin);
        }
        if (! is_numeric($metin)) {
            return null;
        }

        return (float) $metin;
    }

    private function metinMetni(mixed $deger, int $limit): string
    {
        $metin = trim((string) ($deger ?? ''));
        if ($metin === '') {
            return '';
        }

        return mb_substr($metin, 0, $limit);
    }

    private function tarihMetni(mixed $deger): string
    {
        $metin = trim((string) ($deger ?? ''));
        $tarih = \DateTime::createFromFormat('!Y-m-d', $metin);

        return $tarih && $tarih->format('Y-m-d') === $metin ? $metin : '';
    }

    private function kolonAraligi(Builder $sorgu, string $kolon, ?float $min, ?float $max): void
    {
        $kolon = 'tasinmaz_hisseler.'.$kolon;
        if ($min !== null) {
            $sorgu->where($kolon, '>=', $min);
        }
        if ($max !== null) {
            $sorgu->where($kolon, '<=', $max);
        }
    }

    private function ifadeAraligi(Builder $sorgu, string $ifade, ?float $min, ?float $max): void
    {
        if ($min !== null) {
            $sorgu->whereRaw('CAST(('.$ifade.') AS DECIMAL) >= CAST(? AS DECIMAL)', [$min]);
        }
        if ($max !== null) {
            $sorgu->whereRaw('CAST(('.$ifade.') AS DECIMAL) <= CAST(? AS DECIMAL)', [$max]);
        }
    }

    private function tarihAraligi(Builder $sorgu, string $kolon, string $min, string $max): void
    {
        $kolon = 'tasinmaz_hisseler.'.$kolon;
        if ($min !== '') {
            $sorgu->whereDate($kolon, '>=', $min);
        }
        if ($max !== '') {
            $sorgu->whereDate($kolon, '<=', $max);
        }
    }

    private function hisseAdetIfadesi(bool $yalnizAktif): string
    {
        $kosul = $yalnizAktif ? " AND h_adet.hisse_durum = 'aktif'" : '';

        return '(SELECT COUNT(*) FROM tasinmaz_hisseler AS h_adet WHERE h_adet.tasinmaz_id = tasinmaz_hisseler.tasinmaz_id'.$kosul.')';
    }

    public function store(StoreTasinmazHisseRequest $request, int $mahalleTkgmId, string $ada, string $parsel): JsonResponse
    {
        $model = $this->tasinmazBulVeYetkilendir($mahalleTkgmId, $ada, $parsel);
        $veri = $this->hisseVerisi($request);
        $veri['sira'] = (int) ($model->hisseler()->max('sira') ?? -1) + 1;
        $hisse = $model->hisseler()->create($veri);

        return response()->json(['hisse' => $hisse->fresh()->toFormArray()], 201);
    }

    public function update(StoreTasinmazHisseRequest $request, int $mahalleTkgmId, string $ada, string $parsel, int $hisse): JsonResponse
    {
        $model = $this->tasinmazBulVeYetkilendir($mahalleTkgmId, $ada, $parsel);
        $kayit = $model->hisseler()->whereKey($hisse)->firstOrFail();
        $kayit->update($this->hisseVerisi($request));

        return response()->json(['hisse' => $kayit->fresh()->toFormArray()]);
    }

    public function destroy(int $mahalleTkgmId, string $ada, string $parsel, int $hisse): JsonResponse
    {
        $model = $this->tasinmazBulVeYetkilendir($mahalleTkgmId, $ada, $parsel);
        $kayit = $model->hisseler()->whereKey($hisse)->firstOrFail();
        $kayit->delete();

        return response()->json(['ok' => true]);
    }

    private function hisseVerisi(StoreTasinmazHisseRequest $request): array
    {
        $veri = $request->validated();
        if (empty($veri['hisse_durum'])) {
            $veri['hisse_durum'] = 'aktif';
        }

        return $veri;
    }
}
