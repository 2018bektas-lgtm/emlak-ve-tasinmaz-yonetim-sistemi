<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\TasinmazYetkisi;
use App\Http\Requests\StoreTasinmazRequest;
use App\Http\Requests\UpdateTasinmazRequest;
use App\Models\Il;
use App\Models\Ilce;
use App\Models\ImarDurumu;
use App\Models\KayitTuru;
use App\Models\Mahalle;
use App\Models\MevcutKullanimSekli;
use App\Models\Mudurluk;
use App\Models\MuhasebeKayit;
use App\Models\Resim;
use App\Models\Tasinmaz;
use App\Support\TasinmazKolonlari;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TasinmazController extends Controller
{
    use TasinmazYetkisi;

    public function index(Request $request): View
    {
        // Serbest arama
        $q = trim((string) $request->query('q', ''));

        // Cascade lokasyon
        $ilId = $request->query('il_id');
        $ilceId = $request->query('ilce_id');
        $mahalleId = $request->query('mahalle_id');

        // Kadastral
        $nitelik = trim((string) $request->query('nitelik', ''));

        // Durum
        $satisDurumu = $request->query('satis_durumu'); // envanterde|hazirlik|satista|satildi|iptal
        $mulkiyet = $request->query('mulkiyet'); // TAM | HISSELI (aktif hisse üzerinden)

        // Bayrak filtreleri — 1 = zorunlu var, 0 = zorunlu yok, boş = önemsiz
        $bayrakAlanlari = ['kira_var', 'tahsis_var', 'ust_hakki_var', 'meclis_satis_karari_var'];
        $bayrakFiltreleri = [];
        foreach ($bayrakAlanlari as $f) {
            $val = $request->query($f);
            if ($val === '1' || $val === '0') {
                $bayrakFiltreleri[$f] = (int) $val;
            }
        }
        $binaVarMi = $request->query('bina'); // 1 | 0 | null

        $sorgu = Tasinmaz::query()
            ->mudurlukKapsami()
            ->with([
                'il:id,ad',
                'ilce:id,ad',
                'mahalle:id,ad,tkgm_id,ilce_id',
                'mudurluk:id,ad',
                'koordinat:id,tasinmaz_id,lat,lng,koordinat',
                'imar:id,tasinmaz_id,imar_durumu_id,emsal,yenaz_yencok,imar_notu',
                'imar.imarDurumu:id,ad',
                'tapu',
                'hisseler:id,tasinmaz_id,hisse_no,hisse_pay,hisse_payda,hisse_yuzolcum,hisse_durum,islem_tipi',
                'kategori',
                'kategori.muhasebeKayit:id,ad,kod',
                'kategori.kayitTuru:id,ad,kod',
                'ekbilgi',
                'yapilar',
                'yapilar.muhasebeKayit:id,ad,kod',
                'yapilar.kayitTuru:id,ad,kod',
                'resimler',
            ])
            ->withCount('resimler')
            ->when($ilId, fn ($s) => $s->where('il_id', $ilId))
            ->when($ilceId, fn ($s) => $s->where('ilce_id', $ilceId))
            ->when($mahalleId, fn ($s) => $s->where('mahalle_id', $mahalleId))
            ->when($nitelik !== '', fn ($s) => $s->where('nitelik', 'like', "%{$nitelik}%"))
            ->when($binaVarMi === '1', fn ($s) => $s->where('uzeri_bina_var_mi', true))
            ->when($binaVarMi === '0', fn ($s) => $s->where('uzeri_bina_var_mi', false))
            // Satış durumu: ekbilgi VEYA herhangi bir yapıda o durum varsa
            ->when($satisDurumu, function ($s) use ($satisDurumu) {
                $s->where(function ($w) use ($satisDurumu) {
                    $w->whereHas('ekbilgi', fn ($e) => $e->where('satis_durumu', $satisDurumu))
                        ->orWhereHas('yapilar', fn ($y) => $y->where('satis_durumu', $satisDurumu));
                });
            })
            // Bayraklar: ekbilgi'de VEYA yapıda varsa yeter (=1); yokta hiçbir yerde olmasın (=0)
            ->when(! empty($bayrakFiltreleri), function ($s) use ($bayrakFiltreleri) {
                foreach ($bayrakFiltreleri as $alan => $deger) {
                    if ($deger === 1) {
                        $s->where(function ($w) use ($alan) {
                            $w->whereHas('ekbilgi', fn ($e) => $e->where($alan, true))
                                ->orWhereHas('yapilar', fn ($y) => $y->where($alan, true));
                        });
                    } else {
                        $s->whereDoesntHave('ekbilgi', fn ($e) => $e->where($alan, true))
                            ->whereDoesntHave('yapilar', fn ($y) => $y->where($alan, true));
                    }
                }
            })
            // Mülkiyet: aktif hisse sayısı 1 & pay==payda → TAM, aksi HİSSELİ
            ->when($mulkiyet === 'TAM', function ($s) {
                $s->whereHas('hisseler', function ($h) {
                    $h->where('hisse_durum', 'aktif')
                        ->whereColumn('hisse_pay', 'hisse_payda');
                }, '=', 1)->whereHas('hisseler', fn ($h) => $h->where('hisse_durum', 'aktif'), '=', 1);
            })
            ->when($mulkiyet === 'HISSELI', function ($s) {
                $s->where(function ($w) {
                    // Birden fazla aktif hisse
                    $w->whereHas('hisseler', fn ($h) => $h->where('hisse_durum', 'aktif'), '>', 1)
                      // Ya da tek aktif ama pay != payda
                        ->orWhere(function ($ww) {
                            $ww->whereHas('hisseler', fn ($h) => $h->where('hisse_durum', 'aktif'), '=', 1)
                                ->whereDoesntHave('hisseler', function ($h) {
                                    $h->where('hisse_durum', 'aktif')->whereColumn('hisse_pay', 'hisse_payda');
                                });
                        });
                });
            })
            ->when($q, function ($s) use ($q) {
                $s->where(function ($w) use ($q) {
                    $w->where('ada', 'like', "%{$q}%")
                        ->orWhere('parsel', 'like', "%{$q}%")
                        ->orWhere('nitelik', 'like', "%{$q}%")
                        ->orWhereHas('kategori', fn ($k) => $k->where('mevcut_kullanim_sekli', 'like', "%{$q}%"))
                        ->orWhereHas('ekbilgi', fn ($e) => $e->where('aciklama', 'like', "%{$q}%"))
                        ->orWhereHas('yapilar', function ($y) use ($q) {
                            $y->where('mevcut_kullanim_sekli', 'like', "%{$q}%")
                                ->orWhere('aciklama', 'like', "%{$q}%")
                                ->orWhere('bagimsiz_bolum_no', 'like', "%{$q}%");
                        })
                        ->orWhereHas('tapu', fn ($tp) => $tp->where('takbis_zemin_no', 'like', "%{$q}%"));
                });
            })
            ->orderByDesc('id');

        return view('panel.tasinmazlar.index', [
            'tasinmazlar' => $sorgu->paginate(20)->withQueryString(),
            'iller' => Il::orderBy('ad')->get(['id', 'ad']),
            // Cascade için: seçili il varsa ilçeler, seçili ilçe varsa mahalleler
            'ilceler' => $ilId
                ? Ilce::where('il_id', $ilId)->orderBy('ad')->get(['id', 'ad'])
                : collect(),
            'mahalleler' => $ilceId
                ? Mahalle::where('ilce_id', $ilceId)->orderBy('ad')->get(['id', 'ad'])
                : collect(),
            'filtre' => array_merge([
                'q' => $q,
                'il_id' => $ilId,
                'ilce_id' => $ilceId,
                'mahalle_id' => $mahalleId,
                'nitelik' => $nitelik,
                'satis_durumu' => $satisDurumu,
                'mulkiyet' => $mulkiyet,
                'bina' => $binaVarMi,
            ], array_map(fn ($v) => (string) $v, $bayrakFiltreleri)),
            'toplamKayit' => Tasinmaz::query()->mudurlukKapsami()->count(),
            'tumKolonlar' => TasinmazKolonlari::katalog(),
            'kolonGruplari' => TasinmazKolonlari::gruplar(),
            'gorunurKolonlar' => TasinmazKolonlari::kullaniciIcin(auth()->user()),
        ]);
    }

    /**
     * Liste filtrelerini paylaşımlı olarak bir sorguya dönüştürür.
     * export (excel/kml/pdf) ve geojson-filtreli aynı sorguyu kullanır.
     * Eğer `?ids=1,2,3` geldiyse tüm filtreler yok sayılır ve sadece bu id'ler
     * (müdürlük kapsamı çerçevesinde) sorgulanır.
     */
    private function listeSorgusu(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        // Seçime dayalı export — id listesi verilmişse öncelik onda
        if ($request->filled('ids')) {
            $idler = collect(explode(',', (string) $request->query('ids')))
                ->map(fn ($v) => (int) trim($v))
                ->filter(fn ($v) => $v > 0)
                ->values()
                ->all();

            return Tasinmaz::query()
                ->mudurlukKapsami()
                ->whereIn('id', $idler);
        }

        $q = trim((string) $request->query('q', ''));
        $ilId = $request->query('il_id');
        $ilceId = $request->query('ilce_id');
        $mahalleId = $request->query('mahalle_id');
        $nitelik = trim((string) $request->query('nitelik', ''));
        $satisDurumu = $request->query('satis_durumu');
        $mulkiyet = $request->query('mulkiyet');
        $binaVarMi = $request->query('bina');

        $bayrakFiltreleri = [];
        foreach (['kira_var', 'tahsis_var', 'ust_hakki_var', 'meclis_satis_karari_var'] as $f) {
            $val = $request->query($f);
            if ($val === '1' || $val === '0') {
                $bayrakFiltreleri[$f] = (int) $val;
            }
        }

        return Tasinmaz::query()
            ->mudurlukKapsami()
            ->when($ilId, fn ($s) => $s->where('il_id', $ilId))
            ->when($ilceId, fn ($s) => $s->where('ilce_id', $ilceId))
            ->when($mahalleId, fn ($s) => $s->where('mahalle_id', $mahalleId))
            ->when($nitelik !== '', fn ($s) => $s->where('nitelik', 'like', "%{$nitelik}%"))
            ->when($binaVarMi === '1', fn ($s) => $s->where('uzeri_bina_var_mi', true))
            ->when($binaVarMi === '0', fn ($s) => $s->where('uzeri_bina_var_mi', false))
            ->when($satisDurumu, function ($s) use ($satisDurumu) {
                $s->where(function ($w) use ($satisDurumu) {
                    $w->whereHas('ekbilgi', fn ($e) => $e->where('satis_durumu', $satisDurumu))
                        ->orWhereHas('yapilar', fn ($y) => $y->where('satis_durumu', $satisDurumu));
                });
            })
            ->when(! empty($bayrakFiltreleri), function ($s) use ($bayrakFiltreleri) {
                foreach ($bayrakFiltreleri as $alan => $deger) {
                    if ($deger === 1) {
                        $s->where(function ($w) use ($alan) {
                            $w->whereHas('ekbilgi', fn ($e) => $e->where($alan, true))
                                ->orWhereHas('yapilar', fn ($y) => $y->where($alan, true));
                        });
                    } else {
                        $s->whereDoesntHave('ekbilgi', fn ($e) => $e->where($alan, true))
                            ->whereDoesntHave('yapilar', fn ($y) => $y->where($alan, true));
                    }
                }
            })
            ->when($mulkiyet === 'TAM', function ($s) {
                $s->whereHas('hisseler', function ($h) {
                    $h->where('hisse_durum', 'aktif')->whereColumn('hisse_pay', 'hisse_payda');
                }, '=', 1)->whereHas('hisseler', fn ($h) => $h->where('hisse_durum', 'aktif'), '=', 1);
            })
            ->when($mulkiyet === 'HISSELI', function ($s) {
                $s->where(function ($w) {
                    $w->whereHas('hisseler', fn ($h) => $h->where('hisse_durum', 'aktif'), '>', 1)
                        ->orWhere(function ($ww) {
                            $ww->whereHas('hisseler', fn ($h) => $h->where('hisse_durum', 'aktif'), '=', 1)
                                ->whereDoesntHave('hisseler', function ($h) {
                                    $h->where('hisse_durum', 'aktif')->whereColumn('hisse_pay', 'hisse_payda');
                                });
                        });
                });
            })
            ->when($q, function ($s) use ($q) {
                $s->where(function ($w) use ($q) {
                    $w->where('ada', 'like', "%{$q}%")
                        ->orWhere('parsel', 'like', "%{$q}%")
                        ->orWhere('nitelik', 'like', "%{$q}%")
                        ->orWhereHas('kategori', fn ($k) => $k->where('mevcut_kullanim_sekli', 'like', "%{$q}%"))
                        ->orWhereHas('ekbilgi', fn ($e) => $e->where('aciklama', 'like', "%{$q}%"))
                        ->orWhereHas('yapilar', function ($y) use ($q) {
                            $y->where('mevcut_kullanim_sekli', 'like', "%{$q}%")
                                ->orWhere('aciklama', 'like', "%{$q}%")
                                ->orWhere('bagimsiz_bolum_no', 'like', "%{$q}%");
                        })
                        ->orWhereHas('tapu', fn ($tp) => $tp->where('takbis_zemin_no', 'like', "%{$q}%"));
                });
            });
    }

    /**
     * Filtreli taşınmazları görünür kolonlara göre Excel olarak indir.
     */
    public function excelIndir(Request $request): StreamedResponse
    {
        $tasinmazlar = $this->listeSorgusu($request)
            ->with([
                'il:id,ad', 'ilce:id,ad', 'mahalle:id,ad,tkgm_id', 'mudurluk:id,ad',
                'tapu', 'imar.imarDurumu:id,ad',
                'kategori.muhasebeKayit:id,ad,kod',
                'hisseler:id,tasinmaz_id,hisse_durum,hisse_pay,hisse_payda,hisse_yuzolcum',
                'yapilar',
                'ekbilgi', 'koordinat',
            ])
            ->withCount('resimler')
            ->orderBy('id')
            ->get();

        // Görünür kolonlar — kullanıcı seçimine göre (kolon paneli)
        $tumKolonlar = TasinmazKolonlari::katalog();
        $gorunurKolonlar = TasinmazKolonlari::kullaniciIcin(auth()->user());
        // Baştaki sabit "#" (sıra) + kullanıcının seçtiği kolonlar
        $secilenBasliklar = ['#' => 'Sıra No'];
        foreach ($gorunurKolonlar as $anahtar) {
            if (isset($tumKolonlar[$anahtar])) {
                $secilenBasliklar[$anahtar] = $tumKolonlar[$anahtar];
            }
        }

        $ss = new Spreadsheet;
        $sh = $ss->getActiveSheet();
        $sh->setTitle('Taşınmazlar');

        $baslikStil = [
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'D8BC8C']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0E1726']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];
        $ustStil = [
            'font' => ['bold' => true, 'size' => 10],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E9ECEF']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];
        $veriStil = [
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DBE0E8']]],
            'font' => ['size' => 9],
        ];

        // Kolon harfleri (A, B, C, ..., AA, AB) - dinamik sayı
        $kolonAdlari = array_keys($secilenBasliklar);
        $kolonSayisi = count($kolonAdlari);
        $sonHarf = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($kolonSayisi);

        // Ana başlık
        $sh->mergeCells('A1:'.$sonHarf.'1');
        $sh->setCellValue('A1', 'TAŞINMAZ LİSTESİ · '.now()->format('d.m.Y H:i').' · '.$tasinmazlar->count().' kayıt');
        $sh->getStyle('A1')->applyFromArray($baslikStil);
        $sh->getRowDimension(1)->setRowHeight(30);

        // Kolon başlıkları
        $harfKolon = [];
        foreach ($kolonAdlari as $i => $anahtar) {
            $harf = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $harfKolon[$anahtar] = $harf;
            $sh->setCellValue($harf.'2', $secilenBasliklar[$anahtar]);
        }
        $sh->getStyle('A2:'.$sonHarf.'2')->applyFromArray($ustStil);
        $sh->getRowDimension(2)->setRowHeight(28);

        // Satırlar
        $row = 3;
        $sira = 1;
        foreach ($tasinmazlar as $t) {
            foreach ($harfKolon as $anahtar => $harf) {
                if ($anahtar === '#') {
                    $sh->setCellValue($harf.$row, $sira);
                } else {
                    $sh->setCellValue($harf.$row, $this->kolonMetni($t, $anahtar));
                }
            }
            $sira++;
            $row++;
        }
        if ($row > 3) {
            $sh->getStyle('A3:'.$sonHarf.($row - 1))->applyFromArray($veriStil);
        }

        // Kolon genişlikleri
        foreach ($harfKolon as $anahtar => $harf) {
            $sh->getColumnDimension($harf)->setWidth($this->kolonGenisligi($anahtar));
        }

        $filename = 'tasinmazlar-'.now()->format('Y-m-d-Hi').'.xlsx';

        return response()->streamDownload(function () use ($ss) {
            (new Xlsx($ss))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Her kolon anahtarı için taşınmaz veri değerini üretir.
     * Kolon anahtarları TasinmazKolonlari::katalog() ile birebir.
     */
    private function kolonMetni(Tasinmaz $t, string $anahtar): string
    {
        $aktifHisseler = $t->hisseler->where('hisse_durum', 'aktif');
        $bbn = $t->yapilar->first();
        $bayrak = function (string $alan) use ($t) {
            if ($t->ekbilgi && (bool) $t->ekbilgi->{$alan}) {
                return true;
            }

            return $t->yapilar->contains(fn ($y) => (bool) $y->{$alan});
        };

        return (string) match ($anahtar) {
            'tkgm_parsel' => $t->tkgmSorguUrl() ?? '',
            'takbis' => optional($t->tapu)->takbis_zemin_no ?? '',
            'ilce' => optional($t->ilce)->ad ?? '',
            'mahalle' => optional($t->mahalle)->ad ?? '',
            'mudurluk' => optional($t->mudurluk)->ad ?? '',
            'ada' => $t->ada ?? '',
            'parsel' => $t->parsel ?? '',
            'alan' => $t->alan !== null ? number_format((float) $t->alan, 2, ',', '.') : '',
            'nitelik' => $t->nitelik ?? '',
            'hisse_durumu' => $t->mulkiyet_durumu ?? '',
            'hisse_m2' => $aktifHisseler->sum('hisse_yuzolcum') > 0
                ? number_format((float) $aktifHisseler->sum('hisse_yuzolcum'), 2, ',', '.') : '',
            'bb_no' => optional($bbn)->bagimsiz_bolum_no ?? '',
            'blok_no' => optional($bbn)->blok_no ?? '',
            'kat_no' => optional($bbn)->kat_no ?? '',
            'imar' => optional(optional($t->imar)->imarDurumu)->ad ?? '',
            'mulkiyet' => 'Kayıtlı',
            'muhasebe' => optional(optional(optional($t->kategori)->muhasebeKayit))->ad ?? '',
            'dosya' => (string) ($t->resimler_count ?? 0),
            'satis' => $t->satisOzeti()->etiket(),
            'bina' => ((bool) $t->uzeri_bina_var_mi || $t->yapilar->isNotEmpty()) ? 'Var' : 'Yok',
            'aciklama' => trim((string) (optional($t->ekbilgi)->aciklama ?? '').' '.(optional($t->imar)->imar_notu ?? '')),
            'tahsis' => $bayrak('tahsis_var') ? 'Var' : 'Yok',
            'ust_hakki' => $bayrak('ust_hakki_var') ? 'Var' : 'Yok',
            'kira' => $bayrak('kira_var') ? 'Var' : 'Yok',
            'meclis' => $bayrak('meclis_satis_karari_var') ? 'Var' : 'Yok',
            'geometri' => ($t->koordinat && ! empty($t->koordinat->koordinat)) ? 'Var' : 'Yok',
            'ek_rapor' => '',
            default => '',
        };
    }

    /**
     * Excel kolon genişliği (anlamlı defaults).
     */
    private function kolonGenisligi(string $anahtar): int
    {
        return match ($anahtar) {
            '#' => 6,
            'ada', 'parsel', 'kat_no', 'blok_no', 'bb_no', 'bina', 'tahsis',
            'ust_hakki', 'kira', 'meclis', 'geometri', 'dosya' => 9,
            'ilce', 'satis', 'hisse_durumu' => 12,
            'mahalle', 'mudurluk', 'alan', 'hisse_m2', 'takbis',
            'imar', 'mulkiyet' => 16,
            'nitelik', 'muhasebe' => 22,
            'aciklama' => 30,
            'tkgm_parsel' => 40,
            default => 14,
        };
    }

    /**
     * Filtreli taşınmazları KML olarak indir.
     */
    public function kmlIndir(Request $request): Response
    {
        $tasinmazlar = $this->listeSorgusu($request)
            ->with(['koordinat', 'mahalle:id,ad', 'ilce:id,ad'])
            ->whereHas('koordinat')
            ->orderBy('id')
            ->get();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2">'."\n"
            .'<Document>'."\n"
            .'<name>tasinmazlar_'.now()->format('Y-m-d_H-i-s').'.kml</name>'."\n"
            .'<Style id="polygonStyle"><LineStyle><color>ff0e1726</color><width>2.5</width></LineStyle>'
            .'<PolyStyle><color>7fd8bc8c</color></PolyStyle></Style>'."\n"
            .'<Style id="pointStyle"><IconStyle><scale>1.1</scale>'
            .'<Icon><href>http://maps.google.com/mapfiles/kml/pushpin/ylw-pushpin.png</href></Icon>'
            .'</IconStyle></Style>'."\n";

        foreach ($tasinmazlar as $t) {
            $k = $t->koordinat;
            if (! $k) {
                continue;
            }
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

            // GeoJSON Polygon
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
                if (! empty($koordStr) && $koordStr[0] !== end($koordStr)) {
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

        $filename = 'tasinmazlar-'.now()->format('Y-m-d-Hi').'.kml';

        return response($xml, 200, [
            'Content-Type' => 'application/vnd.google-earth.kml+xml',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Yazdırılabilir liste (PDF olarak yazdırılabilir) — görünür kolonlara göre.
     */
    public function pdfIndir(Request $request): View
    {
        $tasinmazlar = $this->listeSorgusu($request)
            ->with([
                'il:id,ad', 'ilce:id,ad', 'mahalle:id,ad,tkgm_id', 'mudurluk:id,ad',
                'tapu', 'imar.imarDurumu:id,ad',
                'kategori.muhasebeKayit:id,ad,kod',
                'hisseler:id,tasinmaz_id,hisse_durum,hisse_pay,hisse_payda,hisse_yuzolcum',
                'yapilar', 'ekbilgi', 'koordinat',
            ])
            ->withCount('resimler')
            ->orderBy('id')
            ->get();

        $tumKolonlar = TasinmazKolonlari::katalog();
        $gorunurKolonlar = TasinmazKolonlari::kullaniciIcin(auth()->user());
        $secilenBasliklar = [];
        foreach ($gorunurKolonlar as $anahtar) {
            if (isset($tumKolonlar[$anahtar])) {
                $secilenBasliklar[$anahtar] = $tumKolonlar[$anahtar];
            }
        }

        return view('panel.tasinmazlar.pdf', [
            'tasinmazlar' => $tasinmazlar,
            'olusturma' => now(),
            'basliklar' => $secilenBasliklar,
            'kolonUret' => fn (Tasinmaz $t, string $anahtar) => $this->kolonMetni($t, $anahtar),
        ]);
    }

    public function ara(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $ilId = $request->query('il_id');
        $ilceId = $request->query('ilce_id');
        $mahalleId = $request->query('mahalle_id');
        $ada = trim((string) $request->query('ada', ''));
        $parsel = trim((string) $request->query('parsel', ''));

        $sorgu = Tasinmaz::query()
            ->mudurlukKapsami()
            ->with(['il:id,ad', 'ilce:id,ad', 'mahalle:id,ad', 'koordinat'])
            ->when($ilId, fn ($s) => $s->where('il_id', $ilId))
            ->when($ilceId, fn ($s) => $s->where('ilce_id', $ilceId))
            ->when($mahalleId, fn ($s) => $s->where('mahalle_id', $mahalleId))
            ->when($ada !== '', fn ($s) => $s->where('ada', $ada))
            ->when($parsel !== '', fn ($s) => $s->where('parsel', $parsel))
            ->when($q, function ($s) use ($q) {
                $s->where(function ($w) use ($q) {
                    $w->where('ada', 'like', "%{$q}%")
                        ->orWhere('parsel', 'like', "%{$q}%")
                        ->orWhere('nitelik', 'like', "%{$q}%")
                        ->orWhereHas('kategori', fn ($k) => $k->where('mevcut_kullanim_sekli', 'like', "%{$q}%"))
                        ->orWhereHas('yapilar', fn ($y) => $y->where('mevcut_kullanim_sekli', 'like', "%{$q}%"));
                });
            })
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json([
            'toplam' => $sorgu->count(),
            'sonuclar' => $sorgu->map(fn ($t) => [
                'id' => $t->id,
                'il' => $t->il->ad ?? null,
                'ilce' => $t->ilce->ad ?? null,
                'mahalle' => $t->mahalle->ad ?? null,
                'ada' => $t->ada,
                'parsel' => $t->parsel,
                'alan' => $t->alan !== null ? (float) $t->alan : null,
                'nitelik' => $t->nitelik,
                'lat' => $t->koordinat->lat ?? null,
                'lng' => $t->koordinat->lng ?? null,
                'geometry' => $t->koordinat->koordinat ?? null,
                'duzenle' => $t->rota('duzenle'),
            ])->values(),
        ]);
    }

    public function show(int $mahalleTkgmId, string $ada, string $parsel): View
    {
        $tasinmaz = $this->tasinmazBulVeYetkilendir($mahalleTkgmId, $ada, $parsel)
            ->load([
                'il:id,ad',
                'ilce:id,ad',
                'mahalle:id,ad,tkgm_id,ilce_id',
                'mudurluk:id,ad',
                'koordinat',
                'imar.imarDurumu:id,ad',
                'tapu',
                'kategori.muhasebeKayit:id,ad,kod',
                'kategori.kayitTuru:id,ad,kod',
                'ekbilgi',
                'hisseler',
                'yapilar.muhasebeKayit:id,ad,kod',
                'yapilar.kayitTuru:id,ad,kod',
                'yapilar.tapu',
                'resimler',
                'meclisKararlari',
            ]);

        return view('panel.tasinmazlar.goster', [
            'tasinmaz' => $tasinmaz,
        ]);
    }

    public function destroy(int $mahalleTkgmId, string $ada, string $parsel): RedirectResponse
    {
        $model = $this->tasinmazBulVeYetkilendir($mahalleTkgmId, $ada, $parsel);
        $id = $model->id;
        $model->delete();

        return redirect()
            ->route('panel.tasinmazlar.index')
            ->with('basari', 'Taşınmaz #'.$id.' silindi.');
    }

    public function create(): View
    {
        return view('panel.tasinmazlar.olustur', [
            'iller' => Il::orderBy('ad')->get(['id', 'ad', 'tkgm_id']),
            'mudurlukler' => Mudurluk::query()->aktif()->orderBy('sira')->get(['id', 'ad']),
            'mudurlukSecilebilir' => auth()->user()?->izinVarMi('tasinmaz.tumunu-gor') ?? false,
            'muhasebeKayitlari' => $this->hiyerarsikSecenekler(MuhasebeKayit::query()),
            'kayitTurleri' => $this->hiyerarsikSecenekler(KayitTuru::query()),
            'mevcutKullanimSekilleri' => MevcutKullanimSekli::where('aktif_mi', true)
                ->orderBy('ad')
                ->get(['id', 'ad']),
            'imarDurumlari' => ImarDurumu::where('aktif_mi', true)
                ->orderBy('ad')
                ->get(['id', 'ad']),
        ]);
    }

    public function store(StoreTasinmazRequest $request): RedirectResponse
    {
        $veri = $request->validated();
        $koordinatVeri = [
            'lat' => $veri['lat'] ?? null,
            'lng' => $veri['lng'] ?? null,
            'koordinat' => isset($veri['koordinat']) ? json_decode($veri['koordinat'], true) : null,
        ];
        $imarVeri = [
            'imar_durumu_id' => $veri['imar_durumu_id'] ?? null,
            'emsal' => $veri['emsal'] ?? null,
            'yenaz_yencok' => $veri['yenaz_yencok'] ?? null,
            'imar_notu' => $veri['imar_notu'] ?? null,
        ];
        $kategoriVeri = $veri['kategori'] ?? [];
        $ekbilgiVeri = $veri['ekbilgi'] ?? [];
        $yapiVeri = $veri['yapi'] ?? null;
        $yapilarVeri = $veri['yapilar'] ?? null;
        $tapu = $veri['tapu'] ?? null;
        $resimler = $request->file('images') ?? [];
        $hisseler = $veri['hisseler'] ?? [];
        $meclisKarariVeri = $veri['meclis_karari'] ?? null;
        $meclisPdf = $request->file('meclis_karari.karar_pdf');
        // Tasinmaz alanlarindan yardimci alanlari ayir
        unset(
            $veri['lat'],
            $veri['lng'],
            $veri['koordinat'],
            $veri['imar_durumu_id'],
            $veri['emsal'],
            $veri['yenaz_yencok'],
            $veri['imar_notu'],
            $veri['kategori'],
            $veri['ekbilgi'],
            $veri['yapi'],
            $veri['yapilar'],
            $veri['tapu'],
            $veri['images'],
            $veri['hisseler'],
            $veri['meclis_karari'],
        );

        $veri = $this->mudurlukIdUygula($veri);

        $tasinmaz = DB::transaction(function () use ($veri, $koordinatVeri, $imarVeri, $kategoriVeri, $ekbilgiVeri, $yapiVeri, $yapilarVeri, $tapu, $resimler, $hisseler, $meclisKarariVeri, $meclisPdf) {
            $tasinmaz = Tasinmaz::create($veri);

            // Sadece harita uzerinden lat/lng verilmisse koordinat kaydi olustur
            if ($koordinatVeri['lat'] !== null && $koordinatVeri['lng'] !== null) {
                $tasinmaz->koordinat()->create($koordinatVeri);
            }

            // Imar bilgisi zorunlu — her zaman kaydedilir
            $tasinmaz->imar()->create($imarVeri);

            // Kategori (arsa seviyesi sınıflandırma) — her taşınmazda tek satır
            $tasinmaz->kategori()->create(array_merge([
                'muhasebe_kayit_id' => null,
                'kayit_turu_id' => null,
                'mevcut_kullanim_sekli' => null,
            ], $kategoriVeri));

            // Ekbilgi (arsa seviyesi durum) — her taşınmazda tek satır
            $tasinmaz->ekbilgi()->create(array_merge([
                'isgal_durumu' => null,
                'satis_durumu' => 'envanterde',
                'meclis_satis_karari_var' => false,
                'tahsis_var' => false,
                'ust_hakki_var' => false,
                'kira_var' => false,
                'aciklama' => null,
            ], $ekbilgiVeri));

            // BBN kayıtları — kayıt tipine göre
            if (is_array($yapiVeri)) {
                // kat_mulkiyetli: tek BBN
                $yapiVeri['sira'] = 0;
                foreach ($yapiVeri as $k => $v) {
                    if ($v === '') {
                        $yapiVeri[$k] = null;
                    }
                }
                $tasinmaz->yapilar()->create($yapiVeri);
            } elseif (is_array($yapilarVeri)) {
                // kat_mulkiyetsiz_bina: çoklu BBN
                foreach (array_values($yapilarVeri) as $sira => $y) {
                    if (! is_array($y)) {
                        continue;
                    }
                    $y['sira'] = $sira;
                    foreach ($y as $k => $v) {
                        if ($v === '') {
                            $y[$k] = null;
                        }
                    }
                    $tasinmaz->yapilar()->create($y);
                }
            }

            // Meclis Satış Kararı — checkbox işaretliyse detayları kaydet
            if (is_array($meclisKarariVeri)) {
                $pdfYolu = null;
                if ($meclisPdf) {
                    $pdfYolu = $meclisPdf->store('meclis_kararlari/'.now()->format('Y/m'), 'public');
                }
                $tasinmaz->meclisKararlari()->create([
                    'karar_tipi' => 'satis',
                    'karar_no' => $meclisKarariVeri['karar_no'] ?? null,
                    'karar_tarihi' => $meclisKarariVeri['karar_tarihi'] ?? null,
                    'karar_ozeti' => $meclisKarariVeri['karar_ozeti'] ?? null,
                    'karar_pdf' => $pdfYolu,
                ]);
            }

            if (is_array($tapu)) {
                $pdf = $tapu['tapu_kaydi_pdf'] ?? null;
                unset($tapu['tapu_kaydi_pdf']);
                if ($pdf) {
                    $tapu['tapu_kaydi_pdf'] = $pdf->store('tapular/'.now()->format('Y/m'), 'public');
                }
                $tasinmaz->tapu()->create($tapu);
            }

            // Hisseler — birden fazla, sirali
            if (! empty($hisseler)) {
                foreach (array_values($hisseler) as $sira => $h) {
                    $h['sira'] = $sira;
                    // Bos alanlari null yap (form'dan bos string gelir)
                    foreach ($h as $k => $v) {
                        if ($v === '') {
                            $h[$k] = null;
                        }
                    }
                    if (empty($h['hisse_durum'])) {
                        $h['hisse_durum'] = 'aktif';
                    }
                    $tasinmaz->hisseler()->create($h);
                }
            }

            // Resim yukleme — ilk resim otomatik kapak, kalanlar sirali
            if (! empty($resimler)) {
                $klasor = 'tasinmazlar/'.$tasinmaz->id.'/'.now()->format('Y/m');
                foreach (array_values($resimler) as $sira => $dosya) {
                    $yol = $dosya->store($klasor, 'public');
                    $tasinmaz->resimler()->create([
                        'dosya_yolu' => $yol,
                        'sira' => $sira,
                        'kapak_mi' => $sira === 0,
                        'mime_type' => $dosya->getMimeType(),
                        'boyut' => $dosya->getSize(),
                    ]);
                }
            }

            return $tasinmaz;
        });

        return redirect()
            ->route('panel.tasinmazlar.index')
            ->with('basari', 'Taşınmaz #'.$tasinmaz->id.' başarıyla oluşturuldu.');
    }

    public function edit(int $mahalleTkgmId, string $ada, string $parsel): View
    {
        $model = $this->tasinmazBulVeYetkilendir($mahalleTkgmId, $ada, $parsel)
            ->load(['koordinat', 'imar', 'tapu', 'resimler', 'hisseler', 'kategori', 'ekbilgi', 'yapilar', 'mahalle', 'mudurluk']);

        // Cascade dropdown'lar icin il seciliyse ilceleri, ilce seciliyse mahalleleri onceden yukle
        $ilceler = $model->il_id
            ? Ilce::where('il_id', $model->il_id)->orderBy('ad')->get(['id', 'ad', 'tkgm_id'])
            : collect();
        $mahalleler = $model->ilce_id
            ? Mahalle::where('ilce_id', $model->ilce_id)->orderBy('ad')->get(['id', 'ad', 'tkgm_id'])
            : collect();

        return view('panel.tasinmazlar.duzenle', [
            'tasinmaz' => $model,
            'iller' => Il::orderBy('ad')->get(['id', 'ad', 'tkgm_id']),
            'mudurlukler' => Mudurluk::query()->orderBy('sira')->get(['id', 'ad']),
            'mudurlukSecilebilir' => auth()->user()?->izinVarMi('tasinmaz.tumunu-gor') ?? false,
            'ilcelerOnceden' => $ilceler,
            'mahallelerOnceden' => $mahalleler,
            'muhasebeKayitlari' => $this->hiyerarsikSecenekler(MuhasebeKayit::query()),
            'kayitTurleri' => $this->hiyerarsikSecenekler(KayitTuru::query()),
            'mevcutKullanimSekilleri' => MevcutKullanimSekli::where('aktif_mi', true)
                ->orderBy('ad')
                ->get(['id', 'ad']),
            'imarDurumlari' => ImarDurumu::where('aktif_mi', true)
                ->orderBy('ad')
                ->get(['id', 'ad']),
        ]);
    }

    public function update(UpdateTasinmazRequest $request, int $mahalleTkgmId, string $ada, string $parsel): RedirectResponse
    {
        $model = $this->tasinmazBulVeYetkilendir($mahalleTkgmId, $ada, $parsel)
            ->load(['koordinat', 'imar', 'tapu', 'resimler', 'hisseler', 'kategori', 'ekbilgi', 'yapilar', 'mahalle']);

        $veri = $this->mudurlukIdUygula($request->validated(), true);
        $koordinatVeri = [
            'lat' => $veri['lat'] ?? null,
            'lng' => $veri['lng'] ?? null,
            'koordinat' => isset($veri['koordinat']) ? json_decode($veri['koordinat'], true) : null,
        ];
        $imarVeri = [
            'imar_durumu_id' => $veri['imar_durumu_id'] ?? null,
            'emsal' => $veri['emsal'] ?? null,
            'yenaz_yencok' => $veri['yenaz_yencok'] ?? null,
            'imar_notu' => $veri['imar_notu'] ?? null,
        ];
        $kategoriVeri = $veri['kategori'] ?? [];
        $ekbilgiVeri = $veri['ekbilgi'] ?? [];
        $yapiVeri = $veri['yapi'] ?? null;
        $yapilarVeri = $veri['yapilar'] ?? null;
        $tapu = $veri['tapu'] ?? null;
        $resimler = $request->file('images') ?? [];
        $silinenResimIdleri = $veri['silinen_resimler'] ?? [];
        $meclisKarariVeri = $veri['meclis_karari'] ?? null;
        $meclisPdf = $request->file('meclis_karari.karar_pdf');

        unset(
            $veri['lat'], $veri['lng'], $veri['koordinat'],
            $veri['imar_durumu_id'], $veri['emsal'], $veri['yenaz_yencok'], $veri['imar_notu'],
            $veri['kategori'], $veri['ekbilgi'], $veri['yapi'], $veri['yapilar'],
            $veri['tapu'], $veri['images'],
            $veri['silinen_resimler'], $veri['hisseler'],
            $veri['meclis_karari'],
        );

        DB::transaction(function () use ($model, $veri, $koordinatVeri, $imarVeri, $kategoriVeri, $ekbilgiVeri, $yapiVeri, $yapilarVeri, $tapu, $resimler, $silinenResimIdleri, $meclisKarariVeri, $meclisPdf) {
            $model->update($veri);

            // Koordinat — updateOrCreate (harita güncellenirse yenilen)
            if ($koordinatVeri['lat'] !== null && $koordinatVeri['lng'] !== null) {
                $model->koordinat()->updateOrCreate(['tasinmaz_id' => $model->id], $koordinatVeri);
            } elseif ($model->koordinat) {
                $model->koordinat->delete();
            }

            // Imar — her zaman zorunlu, updateOrCreate
            $model->imar()->updateOrCreate(['tasinmaz_id' => $model->id], $imarVeri);

            // Kategori (arsa seviyesi) — updateOrCreate
            $model->kategori()->updateOrCreate(['tasinmaz_id' => $model->id], array_merge([
                'muhasebe_kayit_id' => null,
                'kayit_turu_id' => null,
                'mevcut_kullanim_sekli' => null,
            ], $kategoriVeri));

            // Ekbilgi (arsa seviyesi) — updateOrCreate
            $model->ekbilgi()->updateOrCreate(['tasinmaz_id' => $model->id], array_merge([
                'isgal_durumu' => null,
                'satis_durumu' => 'envanterde',
                'meclis_satis_karari_var' => false,
                'tahsis_var' => false,
                'ust_hakki_var' => false,
                'kira_var' => false,
                'aciklama' => null,
            ], $ekbilgiVeri));

            // Meclis Satış Kararı — checkbox işaretliyse upsert, kaldırıldıysa sil.
            $mevcutSatisKararlari = $model->meclisKararlari()->where('karar_tipi', 'satis')->get();
            if (is_array($meclisKarariVeri)) {
                $ilkKarar = $mevcutSatisKararlari->first();
                $pdfYolu = $ilkKarar?->karar_pdf;
                if ($meclisPdf) {
                    if ($pdfYolu) {
                        Storage::disk('public')->delete($pdfYolu);
                    }
                    $pdfYolu = $meclisPdf->store('meclis_kararlari/'.now()->format('Y/m'), 'public');
                }
                $veriler = [
                    'karar_tipi' => 'satis',
                    'karar_no' => $meclisKarariVeri['karar_no'] ?? null,
                    'karar_tarihi' => $meclisKarariVeri['karar_tarihi'] ?? null,
                    'karar_ozeti' => $meclisKarariVeri['karar_ozeti'] ?? null,
                    'karar_pdf' => $pdfYolu,
                ];
                if ($ilkKarar) {
                    $ilkKarar->update($veriler);
                    // Ekstra eski kayıtlar varsa (legacy), silinsin
                    $mevcutSatisKararlari->skip(1)->each->delete();
                } else {
                    $model->meclisKararlari()->create($veriler);
                }
            } else {
                // Checkbox açık değil — mevcut satış kararlarını sil (model booted:
                // deleting event PDF'i de siler)
                $mevcutSatisKararlari->each->delete();
            }

            // BBN — kayıt tipine göre tam yeniden yazma
            if (is_array($yapiVeri)) {
                // kat_mulkiyetli: tek BBN. Diğer yapıları sil ve tek satırı upsert et.
                $mevcutlar = $model->yapilar()->orderBy('sira')->orderBy('id')->get();
                $ilk = $mevcutlar->first();
                foreach ($yapiVeri as $k => $v) {
                    if ($v === '') {
                        $yapiVeri[$k] = null;
                    }
                }
                $yapiVeri['sira'] = 0;
                if ($ilk) {
                    $ilk->update($yapiVeri);
                    $mevcutlar->skip(1)->each->delete();
                } else {
                    $model->yapilar()->create($yapiVeri);
                }
            } elseif (is_array($yapilarVeri)) {
                // kat_mulkiyetsiz_bina: çoklu BBN. Mevcutları temizle ve yeni listeyi yaz.
                // (basit ama güvenli: form submit'te tam liste geliyor)
                $model->yapilar->each->delete();
                foreach (array_values($yapilarVeri) as $sira => $y) {
                    if (! is_array($y)) {
                        continue;
                    }
                    $y['sira'] = $sira;
                    foreach ($y as $k => $v) {
                        if ($v === '') {
                            $y[$k] = null;
                        }
                    }
                    $model->yapilar()->create($y);
                }
            } else {
                // bos_parsel: BBN yok
                $model->yapilar->each->delete();
            }

            // Tapu — morphOne zaten sahip_type/id baglar. updateOrCreate'e
            // Tasinmaz::class vermek morph map alias'i (`tasinmaz`) ile
            // cakisir: kayit bulunamaz, insert unique'e takilir.
            if (is_array($tapu)) {
                $pdf = $tapu['tapu_kaydi_pdf'] ?? null;
                unset($tapu['tapu_kaydi_pdf']);
                if ($pdf) {
                    if ($model->tapu && $model->tapu->tapu_kaydi_pdf) {
                        Storage::disk('public')->delete($model->tapu->tapu_kaydi_pdf);
                    }
                    $tapu['tapu_kaydi_pdf'] = $pdf->store('tapular/'.now()->format('Y/m'), 'public');
                }
                if ($model->tapu) {
                    $model->tapu->update($tapu);
                } else {
                    $model->tapu()->create($tapu);
                }
            }

            // Hisseler modalden AJAX ile kaydedilir; ana form guncellemesi dokunmaz.

            // Silinecek resimler
            if (! empty($silinenResimIdleri)) {
                $model->resimler()
                    ->whereIn('id', $silinenResimIdleri)
                    ->get()
                    ->each(fn (Resim $r) => $r->delete()); // model booted() dosyayi da siler
            }

            // Yeni resimler ekle
            if (! empty($resimler)) {
                $mevcutSayi = $model->resimler()->count();
                $mevcutKapak = $model->resimler()->where('kapak_mi', true)->exists();
                $klasor = 'tasinmazlar/'.$model->id.'/'.now()->format('Y/m');
                foreach (array_values($resimler) as $i => $dosya) {
                    $yol = $dosya->store($klasor, 'public');
                    $model->resimler()->create([
                        'dosya_yolu' => $yol,
                        'sira' => $mevcutSayi + $i,
                        // Ilk resim eklenmisse ve mevcut hicbiri kapak degilse kapak yap
                        'kapak_mi' => ($i === 0 && ! $mevcutKapak),
                        'mime_type' => $dosya->getMimeType(),
                        'boyut' => $dosya->getSize(),
                    ]);
                }
            }
        });

        // ada/parsel/mahalle değişmiş olabilir — model'i yenile ki yeni slug hesaplansın
        $model->refresh()->load('mahalle');

        return redirect()
            ->route('panel.tasinmazlar.duzenle', $model->duzenleParams())
            ->with('basari', 'Taşınmaz #'.$model->id.' başarıyla güncellendi.');
    }

    public function harita(Request $request): View
    {
        $secilenIdler = null;
        if ($request->filled('ids')) {
            $secilenIdler = collect(explode(',', (string) $request->query('ids')))
                ->map(fn ($v) => (int) trim($v))
                ->filter(fn ($v) => $v > 0)
                ->values()
                ->all();
        }

        return view('panel.tasinmazlar.harita', [
            'iller' => Il::orderBy('ad')->get(['id', 'ad']),
            'secilenIdler' => $secilenIdler,
        ]);
    }

    public function geojson(Request $request): JsonResponse
    {
        // Filtre parametresi: ?ids=1,2,3 — sadece belirtilen taşınmazları döner
        $idler = null;
        if ($request->filled('ids')) {
            $idler = collect(explode(',', (string) $request->query('ids')))
                ->map(fn ($v) => (int) trim($v))
                ->filter(fn ($v) => $v > 0)
                ->values()
                ->all();
        }

        $kayitlar = Tasinmaz::query()
            ->mudurlukKapsami()
            ->with([
                'il:id,ad',
                'ilce:id,ad',
                'mahalle:id,ad,tkgm_id',
                'koordinat:id,tasinmaz_id,lat,lng,koordinat',
                'imar:id,tasinmaz_id,imar_durumu_id',
                'imar.imarDurumu:id,ad',
            ])
            ->whereHas('koordinat')
            ->when(is_array($idler) && ! empty($idler), fn ($q) => $q->whereIn('id', $idler))
            ->get(['id', 'il_id', 'ilce_id', 'mahalle_id', 'ada', 'parsel', 'alan', 'nitelik']);

        $features = [];
        foreach ($kayitlar as $t) {
            $k = $t->koordinat;
            if (! $k) {
                continue;
            }
            $geometry = $this->koordinatGeometri($k);
            if ($geometry === null) {
                continue;
            }
            $features[] = [
                'type' => 'Feature',
                'id' => $t->id,
                'geometry' => $geometry,
                'properties' => [
                    'id' => $t->id,
                    'ada' => $t->ada,
                    'parsel' => $t->parsel,
                    'alan' => $t->alan,
                    'nitelik' => $t->nitelik,
                    'il' => $t->il?->ad,
                    'ilce' => $t->ilce?->ad,
                    'mahalle' => $t->mahalle?->ad,
                    'mahalle_tkgm_id' => $t->mahalle?->tkgm_id,
                    'imar' => $t->imar?->imarDurumu?->ad,
                    'duzenle' => $t->rota('duzenle'),
                ],
            ];
        }

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    /**
     * Parametrik hiyerarsik tabloyu tree-select icin duzler.
     * Etiket sadece "[KOD] Ad" — indent'i frontend (aselect tree-mode) yapar.
     *
     * @param  Builder<Model>  $sorgu
     * @return array<int, array{id:int, parent_id:?int, seviye:int, kod:?string, ad:string, etiket:string}>
     */
    private function hiyerarsikSecenekler($sorgu): array
    {
        $tumu = $sorgu->select('id', 'parent_id', 'ad', 'kod', 'sira')
            ->where('aktif_mi', true)
            ->orderBy('sira')
            ->orderBy('ad')
            ->get();

        $cocuklarGruplu = $tumu->groupBy('parent_id');
        $sonuc = [];

        $yerlestir = function ($parentId, int $seviye) use (&$yerlestir, $cocuklarGruplu, &$sonuc): void {
            foreach ($cocuklarGruplu->get($parentId, collect()) as $kayit) {
                $kodEki = $kayit->kod ? '['.$kayit->kod.'] ' : '';
                $sonuc[] = [
                    'id' => $kayit->id,
                    'parent_id' => $kayit->parent_id,
                    'seviye' => $seviye,
                    'kod' => $kayit->kod,
                    'ad' => $kayit->ad,
                    'etiket' => $kodEki.$kayit->ad,
                ];
                $yerlestir($kayit->id, $seviye + 1);
            }
        };

        $yerlestir(null, 0);

        return $sonuc;
    }

    /**
     * TasinmazKoordinat kaydini GeoJSON geometriye cevirir.
     *
     * @return array{type:string, coordinates:mixed}|null
     */
    private function koordinatGeometri($kayit): ?array
    {
        $geom = $kayit->koordinat;
        if (is_string($geom)) {
            $geom = json_decode($geom, true);
        }
        if (is_array($geom) && isset($geom['type'], $geom['coordinates'])) {
            return $geom;
        }
        if (is_array($geom) && isset($geom[0][0]) && is_numeric($geom[0][0])) {
            $halka = $geom;
            $ilk = $halka[0];
            $son = $halka[count($halka) - 1];
            if ($ilk[0] != $son[0] || $ilk[1] != $son[1]) {
                $halka[] = $ilk;
            }

            return ['type' => 'Polygon', 'coordinates' => [$halka]];
        }
        if ($kayit->lat !== null && $kayit->lng !== null) {
            return [
                'type' => 'Point',
                'coordinates' => [(float) $kayit->lng, (float) $kayit->lat],
            ];
        }

        return null;
    }
}
