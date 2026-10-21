<?php

namespace App\Http\Controllers;

use App\Models\KiraAlan;
use App\Models\KiraPoligon;
use App\Support\Geometri;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Kira haritasının seçim ve kayıt araçları.
 *
 * Referans sistemin KIRA_PANELI araç çubuğundaki işlemlerin karşılığı:
 * seçim için Nokta Bilgisi / Alan İçinde Kalanlar / Çizgiyi Kesenler /
 * Pencere, kayıt için Yeni Kayıt / Kayıt Güncelle / Kayıt Sil.
 *
 * Geometri spatial sütunda değil GeoJSON olarak tutuluyor; her sorgu önce
 * indexli sınır kutusuyla kaba süzülür, kesin test (App\Support\Geometri)
 * yalnızca kutusu çakışan kayıtlara uygulanır.
 */
class KiraHaritaController extends Controller
{
    /** Bir seçimde en fazla kaç kayıt döner — geniş seçim ekranı kilitlemesin. */
    private const SINIR = 300;

    /**
     * Seçim araçları. İstek gövdesi:
     *   tur=nokta   → nokta: [lng, lat]
     *   tur=pencere → pencere: [minLng, minLat, maxLng, maxLat]
     *   tur=alan    → halka: [[lng, lat], ...]
     *   tur=cizgi   → cizgi: [[lng, lat], ...]
     */
    public function secim(Request $request): JsonResponse
    {
        $veri = $request->validate([
            'tur' => ['required', Rule::in(['nokta', 'pencere', 'alan', 'cizgi'])],
            'nokta' => ['required_if:tur,nokta', 'array', 'size:2'],
            'nokta.*' => ['numeric'],
            'pencere' => ['required_if:tur,pencere', 'array', 'size:4'],
            'pencere.*' => ['numeric'],
            'halka' => ['required_if:tur,alan', 'array', 'min:3', 'max:500'],
            'cizgi' => ['required_if:tur,cizgi', 'array', 'min:2', 'max:500'],
            // Alan İçinde Kalanlar: tamamen içinde mi, kesişen de olsun mu
            'kesisen' => ['nullable', 'boolean'],
        ]);

        [$bbox, $test] = match ($veri['tur']) {
            'nokta' => $this->noktaTesti($veri['nokta']),
            'pencere' => $this->pencereTesti($veri['pencere'], $request->boolean('kesisen')),
            'alan' => $this->halkaTesti($this->noktaDizisi($veri['halka']), $request->boolean('kesisen')),
            'cizgi' => $this->cizgiTesti($this->noktaDizisi($veri['cizgi'])),
        };

        // Kaba süzme: sınır kutusu index üzerinden
        $poligonlar = KiraPoligon::query()->alanda($bbox)->limit(2000)->get();

        $eslesen = $poligonlar->filter(function (KiraPoligon $p) use ($test) {
            $geo = $p->geoJson();

            return $geo && $test($geo);
        });

        if ($eslesen->isEmpty()) {
            return response()->json(['toplam' => 0, 'kayitlar' => []]);
        }

        return response()->json($this->kayitlar($eslesen->pluck('id')));
    }

    /**
     * Yeni Kayıt: haritada çizilen sınır poligon olarak saklanır, istemci de
     * dönen poligon kimliğiyle alan formunu açar.
     */
    public function poligonKaydet(Request $request): JsonResponse
    {
        $veri = $this->poligonDogrula($request);
        $this->bosCizimleriTemizle();

        $poligon = new KiraPoligon;
        $poligon->fill([
            'ad' => $veri['ad'] ?? null,
            'kaynak' => $veri['kaynak'] ?? 'cizim',
            'mahalle_id' => $veri['mahalle_id'] ?? null,
            'ada' => $veri['ada'] ?? null,
            'parsel' => $veri['parsel'] ?? null,
            'koordinat' => $veri['koordinat'],
            'alan' => $veri['alan'] ?? null,
            'olusturan_id' => $request->user()?->id,
        ]);
        $merkez = $poligon->sinirKutusu();
        $poligon->lat = $merkez ? $merkez['merkez_lat'] : null;
        $poligon->lng = $merkez ? $merkez['merkez_lng'] : null;
        $poligon->save();

        return response()->json([
            'poligon_id' => $poligon->id,
            'form' => route('panel.kira.alanlar.olustur', ['poligon' => $poligon->id]),
            'mesaj' => 'Sınır kaydedildi. Şimdi kayıt bilgilerini girin.',
        ], 201);
    }

    /** Kayıt Güncelle: var olan poligonun geometrisi değiştirilir. */
    public function poligonGuncelle(Request $request, int $id): JsonResponse
    {
        $veri = $this->poligonDogrula($request);
        $poligon = KiraPoligon::findOrFail($id);

        $poligon->koordinat = $veri['koordinat'];
        if (array_key_exists('alan', $veri) && $veri['alan'] !== null) {
            $poligon->alan = $veri['alan'];
        }
        if (! empty($veri['ad'])) {
            $poligon->ad = $veri['ad'];
        }
        $merkez = $poligon->sinirKutusu();
        $poligon->lat = $merkez ? $merkez['merkez_lat'] : null;
        $poligon->lng = $merkez ? $merkez['merkez_lng'] : null;
        $poligon->save();

        return response()->json([
            'poligon_id' => $poligon->id,
            'kayit_sayisi' => $poligon->kayitSayisi(),
            'mesaj' => 'Sınır güncellendi.',
        ]);
    }

    /**
     * Kayıt Sil: poligonu siler. Poligon başka kayıtlar da kullanıyor
     * olabileceği için (aynı sınırda birden çok kira kaydı) kullanımdaysa
     * silinmez; kullanıcıya hangi kayıtların bağlı olduğu bildirilir.
     */
    public function poligonSil(int $id): JsonResponse
    {
        $poligon = KiraPoligon::findOrFail($id);
        $sayi = $poligon->kayitSayisi();

        if ($sayi > 0) {
            return response()->json([
                'silindi' => false,
                'kayit_sayisi' => $sayi,
                'mesaj' => "Bu sınır {$sayi} kayıtta kullanılıyor. Önce kayıtların sınır bağını kaldırın.",
            ] + $this->kayitlar(collect([$poligon->id])), 409);
        }

        $poligon->delete();

        return response()->json(['silindi' => true, 'mesaj' => 'Sınır silindi.']);
    }

    /**
     * Haritada bir sınıra tıklandığında yan panele basılan içerik.
     *
     * Aynı poligonda birden çok kira kaydı olabildiği için hepsi döner;
     * panel bunları dosya numarasına göre açılır listede gösterir.
     */
    public function poligonKayitlari(int $id): JsonResponse
    {
        $poligon = KiraPoligon::findOrFail($id);

        $alanlar = KiraAlan::query()
            ->where('poligon_id', $poligon->id)
            ->with([
                'dosya:id,dosya_no,eski_dosya_no,isyeri_adi,ihale_turu,sozlesme_aktif_mi,uyari',
                'dosya.kiracilar:id,kira_dosya_id,kira_sozlesme_id,tip,ad_unvan,telefon',
                'dosya.aktifSozlesme',
                'dosya.aktifSozlesme.bedeller',
                'dosya.tasinmaz:id,kira_dosya_id,ilce_id,mahalle_id,ada,parsel,adres,yuzolcum,imar_durumu_id',
                'dosya.tasinmaz.ilce:id,ad',
                'dosya.tasinmaz.mahalle:id,ad',
                'dosya.tasinmaz.imarDurumu:id,ad',
                'isKolu:id,ad',
                'kiracilikDurum:id,ad',
                'hukukiDurum:id,ad',
            ])
            // Dolu kayıt önce: panel açılışında yürürlükteki kira görünsün
            ->orderByRaw("CASE WHEN dolu_bos = 'dolu' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'poligon' => [
                'id' => $poligon->id,
                'ad' => $poligon->ad,
                'ada_parsel' => $poligon->adaParsel(),
                'alan' => $poligon->alan !== null ? (float) $poligon->alan : null,
                'kaynak' => $poligon->kaynak,
                'lat' => $poligon->merkez_lat !== null ? (float) $poligon->merkez_lat : null,
                'lng' => $poligon->merkez_lng !== null ? (float) $poligon->merkez_lng : null,
            ],
            'kayitlar' => $alanlar->map(function (KiraAlan $a) {
                $d = $a->dosya;
                $t = $d?->tasinmaz;
                $s = $d?->aktifSozlesme;
                $bedel = $s?->guncelBedel();
                $kiraci = $d?->guncelKiraci();

                return [
                    'alan_id' => $a->id,
                    'dosya_id' => $a->kira_dosya_id,
                    'dosya_no' => $d?->dosya_no,
                    'eski_dosya_no' => $d?->eski_dosya_no,
                    'ad' => $a->ad ?: $d?->isyeri_adi,
                    'kiraci' => $a->kiraci(),
                    'telefon' => $kiraci?->telefon,
                    'alt_kiraci' => $a->alt_kiraci,
                    'is_kolu' => $a->isKolu?->ad,
                    'faaliyet' => $a->faaliyet_konusu,
                    'dolu_bos' => $a->dolu_bos->value,
                    'dolu_bos_ad' => $a->dolu_bos->etiket(),
                    'ihale' => $d?->ihale_turu->etiket(),
                    'sozlesme_aktif' => (bool) ($d?->sozlesme_aktif_mi),
                    'kiracilik_durum' => $a->kiracilikDurum?->ad,
                    'hukuki_durum' => $a->hukukiDurum?->ad,
                    'uyari' => $d?->uyari,
                    // Alanın kendi dönemi yoksa dosyanın yürürlükteki sözleşmesi
                    'baslangic' => ($a->baslangic_tarihi ?? $s?->baslangic_tarihi)?->toDateString(),
                    'bitis' => ($a->bitis_tarihi ?? $s?->bitis_tarihi)?->toDateString(),
                    'kalan_gun' => $a->kalanGun() ?? ($d?->sozlesme_aktif_mi ? $s?->kalanGun() : null),
                    'yillik_bedel' => $a->yillik_bedel !== null
                        ? (float) $a->yillik_bedel
                        : ($bedel?->yillik_bedel !== null ? (float) $bedel->yillik_bedel : null),
                    'taksit' => $a->taksit_sayisi ?? $s?->taksit_sayisi,
                    'alan' => $a->alan !== null ? (float) $a->alan : null,
                    'ilce' => $t?->ilce?->ad,
                    'mahalle' => $t?->mahalle?->ad,
                    'ada_parsel' => $t?->adaParsel(),
                    'imar' => $t?->imarDurumu?->ad,
                    'adres' => $a->adres ?: $t?->adres,
                    'detay' => route('panel.kira.alanlar.detay', $a->id),
                    'duzenle' => route('panel.kira.alanlar.duzenle', $a->id),
                    'dosya_detay' => $a->kira_dosya_id ? route('panel.kira.detay', $a->kira_dosya_id) : null,
                ];
            })->values(),
            'ekle' => route('panel.kira.alanlar.olustur', ['poligon' => $poligon->id]),
        ]);
    }

    /** Bir kaydın sınır bağını kaldırır — poligon silinebilir hâle gelir. */
    public function alanSinirAyir(int $alanId): JsonResponse
    {
        $alan = KiraAlan::findOrFail($alanId);
        $alan->update(['poligon_id' => null]);

        return response()->json(['mesaj' => 'Kaydın sınır bağı kaldırıldı.']);
    }

    /**
     * Haritada çizilip formu tamamlanmayan sınırlar artakalır. Yeni bir çizim
     * yapılırken, bir günden eski ve hiçbir kayda bağlı olmayan çizimler
     * temizlenir — TKGM'den gelen ya da içe aktarılan poligonlara dokunulmaz.
     */
    private function bosCizimleriTemizle(): void
    {
        $idler = KiraPoligon::query()
            ->where('kaynak', 'cizim')
            ->where('created_at', '<', now()->subDay())
            ->whereDoesntHave('alanlar')
            ->limit(100)
            ->pluck('id');

        if ($idler->isNotEmpty()) {
            KiraPoligon::whereIn('id', $idler)->delete();
        }
    }

    // ------------------------------------------------------------------
    // Seçim testleri — her biri [bbox, test closure] döner
    // ------------------------------------------------------------------

    /** @return array{0: array<int, float>, 1: callable} */
    private function noktaTesti(array $nokta): array
    {
        [$lng, $lat] = array_map('floatval', $nokta);
        // Nokta için kutuyu biraz genişlet: tam kenara basıldığında da bulunsun
        $d = 0.00002;

        return [[$lng - $d, $lat - $d, $lng + $d, $lat + $d],
            fn (array $geo) => Geometri::noktaIcinde($geo, $lng, $lat)];
    }

    /** @return array{0: array<int, float>, 1: callable} */
    private function pencereTesti(array $pencere, bool $kesisen): array
    {
        [$minLng, $minLat, $maxLng, $maxLat] = array_map('floatval', $pencere);
        $halka = Geometri::pencereHalkasi(
            min($minLng, $maxLng), min($minLat, $maxLat),
            max($minLng, $maxLng), max($minLat, $maxLat)
        );

        return $this->halkaTesti($halka, $kesisen);
    }

    /** @return array{0: array<int, float>, 1: callable} */
    private function halkaTesti(array $halka, bool $kesisen): array
    {
        if ($halka[0] !== end($halka)) {
            $halka[] = $halka[0];
        }

        return [Geometri::sinirKutusu($halka), $kesisen
            ? fn (array $geo) => Geometri::kesisiyor($geo, $halka)
            : fn (array $geo) => Geometri::tamamenIcinde($geo, $halka)];
    }

    /** @return array{0: array<int, float>, 1: callable} */
    private function cizgiTesti(array $cizgi): array
    {
        return [Geometri::sinirKutusu($cizgi), fn (array $geo) => Geometri::cizgiKesiyor($geo, $cizgi)];
    }

    /** @param  list<array{0: float, 1: float}>|array<int, mixed>  $ham */
    private function noktaDizisi(array $ham): array
    {
        $noktalar = [];
        foreach ($ham as $n) {
            if (is_array($n) && isset($n[0], $n[1]) && is_numeric($n[0]) && is_numeric($n[1])) {
                $noktalar[] = [(float) $n[0], (float) $n[1]];
            }
        }
        abort_if(count($noktalar) < 2, 422, 'Geçerli geometri alınamadı.');

        return $noktalar;
    }

    /**
     * Seçilen poligonlardaki kira kayıtları — harita sonuç listesi için.
     *
     * @param  Collection<int, int>  $poligonIdleri
     * @return array<string, mixed>
     */
    private function kayitlar(Collection $poligonIdleri): array
    {
        $alanlar = KiraAlan::query()
            ->whereIn('poligon_id', $poligonIdleri)
            ->with([
                'dosya:id,dosya_no,isyeri_adi',
                'dosya.anaKiraci',
                'dosya.tasinmaz:id,kira_dosya_id,ilce_id,mahalle_id,ada,parsel',
                'dosya.tasinmaz.ilce:id,ad', 'dosya.tasinmaz.mahalle:id,ad',
                'isKolu:id,ad',
                'poligon:id,ad,merkez_lat,merkez_lng',
            ])
            ->orderBy('poligon_id')->orderByDesc('id')
            ->limit(self::SINIR)
            ->get();

        $toplam = KiraAlan::whereIn('poligon_id', $poligonIdleri)->count();

        return [
            'toplam' => $toplam,
            'kesilen' => $toplam > self::SINIR,
            'poligon_sayisi' => $poligonIdleri->count(),
            'kayitlar' => $alanlar->map(function (KiraAlan $a) {
                $t = $a->dosya?->tasinmaz;

                return [
                    'alan_id' => $a->id,
                    'poligon_id' => $a->poligon_id,
                    'dosya_id' => $a->kira_dosya_id,
                    'dosya_no' => $a->dosya?->dosya_no,
                    'ad' => $a->ad ?: $a->dosya?->isyeri_adi,
                    'kiraci' => $a->kiraci(),
                    'is_kolu' => $a->isKolu?->ad,
                    'ada_parsel' => $t?->adaParsel(),
                    'ilce' => $t?->ilce?->ad,
                    'mahalle' => $t?->mahalle?->ad,
                    'alan' => $a->alan !== null ? (float) $a->alan : null,
                    'dolu_bos' => $a->dolu_bos->value,
                    'dolu_bos_ad' => $a->dolu_bos->etiket(),
                    'bitis' => $a->bitis_tarihi?->toDateString(),
                    'kalan_gun' => $a->kalanGun(),
                    'lat' => $a->poligon?->merkez_lat !== null ? (float) $a->poligon->merkez_lat : null,
                    'lng' => $a->poligon?->merkez_lng !== null ? (float) $a->poligon->merkez_lng : null,
                    'detay' => route('panel.kira.alanlar.detay', $a->id),
                    'duzenle' => route('panel.kira.alanlar.duzenle', $a->id),
                ];
            })->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function poligonDogrula(Request $request): array
    {
        $veri = $request->validate([
            'koordinat' => ['required', 'array'],
            'koordinat.type' => ['required', Rule::in(['Polygon', 'MultiPolygon', 'Point', 'LineString'])],
            'koordinat.coordinates' => ['required', 'array'],
            'ad' => ['nullable', 'string', 'max:200'],
            'kaynak' => ['nullable', Rule::in(['tkgm', 'cizim', 'ice_aktarim'])],
            'mahalle_id' => ['nullable', 'exists:mahalleler,id'],
            'ada' => ['nullable', 'string', 'max:20'],
            'parsel' => ['nullable', 'string', 'max:20'],
            'alan' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
        ]);

        // Noktası olmayan geometri sınır kutusu üretemez, kayda değmez
        $nokta = 0;
        array_walk_recursive($veri['koordinat']['coordinates'], function () use (&$nokta) {
            $nokta++;
        });
        abort_if($nokta < 2, 422, 'Geçerli bir sınır çizilmedi.');

        return $veri;
    }
}
