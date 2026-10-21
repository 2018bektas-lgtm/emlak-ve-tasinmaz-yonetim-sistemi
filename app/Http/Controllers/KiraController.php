<?php

namespace App\Http\Controllers;

use AppEnumsKiraciTipi;
use App\Enums\KiraDoluBos;
use App\Enums\KiraciTipi;
use App\Models\Il;
use App\Models\Ilce;
use App\Models\ImarDurumu;
use App\Models\KiraDosyasi;
use App\Models\KiraHukukiDurum;
use App\Models\KiraAlan;
use App\Models\KiraIsKolu;
use App\Models\KiraKiraci;
use App\Models\KiraKiracilikDurum;
use App\Models\KiraPoligon;
use App\Models\KiraMuhasebeNiteligi;
use App\Models\KiraSozlesme;
use App\Models\KiraTasinmaz;
use App\Models\Mahalle;
use App\Support\HaritaGorunum;
use App\Support\KiraKolonlari;
use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
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
                'kiracilar:id,kira_dosya_id,tip,ad_unvan,tc_vergi_no,telefon',
                'isKolu:id,ad',
                'anaDosya:id,dosya_no',
                'hukukiDurum:id,ad',
                'kiracilikDurum:id,ad',
                'muhasebeNiteligi:id,ad',
                'sorumluPersonel:id,ad,soyad',
                'tasinmaz:id,kira_dosya_id,ilce_id,mahalle_id,ada,parsel,adres,yuzolcum,imar_durumu_id',
                'tasinmaz.ilce:id,ad',
                'tasinmaz.mahalle:id,ad',
                'tasinmaz.imarDurumu:id,ad',
                // latestOfMany kendi alt sorgusunu kurduğu için kolon kısıtlaması
                // yapılmaz; kısıtlanırsa kira_dosya_id belirsiz kalıyor.
                'aktifSozlesme',
                'aktifSozlesme.bedeller',
            ])
            ->orderByDesc('id');

        if ($request->boolean('excel')) {
            // Excel ana kiracıyı satır satır okuyor; toplu yüklenmezse her satır ayrı sorgu
            return $this->excelYaz($sorgu->with('anaKiraci:id,kira_dosya_id,ad_unvan')->get());
        }

        $gorunurKolonlar = KiraKolonlari::kullaniciIcin($request->user());
        $sayfaBoyu = $this->sayfaBoyu($request);

        return view('panel.kira.index', [
            'kayitlar' => $sorgu->paginate($sayfaBoyu)->withQueryString()->appends(['per_page' => $sayfaBoyu]),
            'sayfaBoyu' => $sayfaBoyu,
            'sayac' => $this->sayaclar($f),
            'ozet' => $this->ozet($taban),
            'filtre' => $f,
            'isKollari' => KiraIsKolu::aktif()->get(['id', 'ad']),
            'hukukiDurumlar' => KiraHukukiDurum::aktif()->get(['id', 'ad']),
            'kiracilikDurumlar' => KiraKiracilikDurum::aktif()->get(['id', 'ad']),
            'ilceler' => Ilce::orderBy('ad')->get(['id', 'ad']),
            'mahalleler' => $f['ilce_id'] ? Mahalle::where('ilce_id', $f['ilce_id'])->orderBy('ad')->get(['id', 'ad']) : collect(),
            // Kolon seçimi
            'kolonKatalog' => KiraKolonlari::katalog(),
            'kolonGruplari' => KiraKolonlari::gruplar(),
            'gorunurKolonlar' => $gorunurKolonlar,
            'varsayilanKolonlar' => KiraKolonlari::varsayilan(),
            'secilenSayi' => count($gorunurKolonlar),
            'toplamKolon' => count(KiraKolonlari::katalog()),
        ]);
    }

    public function excelIndir(Request $request): StreamedResponse
    {
        return $this->index($request->merge(['excel' => 1]));
    }

    /**
     * Dosya detayı. Referans sistemin "Bilgi Formu"nun karşılığı: sözleşmesel
     * bilgiler, belgeler, hukuki bilgiler, adres/tapu ve harita — artı bizde
     * ayrı tablolarda duran bedel geçmişi, ihale ve süreç olayları.
     */
    public function detay(int $id): View
    {
        $dosya = KiraDosyasi::with([
            'kiracilar',
            'isKolu', 'anaDosya', 'altDosyalar:id,dosya_no,isyeri_adi',
            'hukukiDurum', 'kiracilikDurum', 'muhasebeNiteligi', 'sorumluPersonel',
            'tasinmaz.il', 'tasinmaz.ilce', 'tasinmaz.mahalle', 'tasinmaz.imarDurumu', 'tasinmaz.tasinmaz',
            'sozlesmeler.bedeller',
            'alanlar.isKolu', 'alanlar.poligon', 'alanlar.kiracilikDurum', 'alanlar.hukukiDurum', 'alanlar.belgeler',
            'ihaleler.komisyon', 'ihaleler.katilimcilar',
            'kararlar',
            'surecOlaylari.olusturan',
            'isletmeBilgisi',
            'belgeler.yukleyen',
        ])->findOrFail($id);

        return view('panel.kira.detay', [
            'dosya' => $dosya,
            'birincilPoligon' => $dosya->alanlar->firstWhere('poligon_id', '!=', null)?->poligon,
            'aktifSozlesme' => $dosya->sozlesmeler->firstWhere('aktif_mi', true) ?? $dosya->sozlesmeler->last(),
        ]);
    }

    public function harita(Request $request): View
    {
        $kullanilan = KiraTasinmaz::query()->whereNotNull('imar_durumu_id')->distinct()->pluck('imar_durumu_id');

        return view('panel.kira.harita', [
            'iller' => Il::orderBy('ad')->get(['id', 'ad']),
            'isKollari' => KiraIsKolu::aktif()->get(['id', 'ad']),
            'ekleyebilir' => $request->user()->izinVarMi('kira.olustur'),
            'imarDurumlari' => ImarDurumu::whereIn('id', $kullanilan)->orderBy('sira')->orderBy('ad')->get(['id', 'ad']),
        ]);
    }

    /**
     * Haritanın kira katmanı. Dosya değil **konum** çizilir: geometri
     * kira_koordinatlari'nda, bbox indexli — değerleme/emsal katmanlarıyla
     * aynı kümeleme sözleşmesini konuşur.
     */
    public function haritaGeojson(Request $request): JsonResponse
    {
        $imar = DegerlemeController::imarSuzgeci($request);
        $f = $this->filtre($request);

        $dosyaIdleri = $this->sorgu($f)->select('id');

        // Harita artık alanları çizer: bir dosyanın birden çok kira alanı,
        // her alanın da paylaşılabilir bir poligonu olabiliyor.
        $alanIdleri = KiraAlan::query()
            ->whereIn('kira_dosya_id', $dosyaIdleri)
            ->whereNotNull('poligon_id')
            ->select('poligon_id');

        $poligonlar = KiraPoligon::query()
            ->whereIn('id', $alanIdleri)
            ->when($imar !== null, fn ($s) => $s->whereIn('id', KiraAlan::query()->select('poligon_id')
                ->whereIn('kira_dosya_id', KiraTasinmaz::query()->select('kira_dosya_id')
                    ->where(function ($w) use ($imar) {
                        if ($imar['idler'] === [] && ! $imar['yok']) {
                            $w->whereRaw('1 = 0');

                            return;
                        }
                        if ($imar['idler'] !== []) {
                            $w->whereIn('imar_durumu_id', $imar['idler']);
                        }
                        if ($imar['yok']) {
                            $w->orWhereNull('imar_durumu_id');
                        }
                    }))));

        if ($request->boolean('kapsam')) {
            return response()->json(HaritaGorunum::kapsam($poligonlar));
        }

        $bbox = HaritaGorunum::bbox($request);
        if ($bbox && ($kume = HaritaGorunum::kumeler($poligonlar, $bbox, HaritaGorunum::zoom($request)))) {
            return response()->json(['type' => 'FeatureCollection', 'kumeli' => true] + $kume);
        }

        $gorunen = (clone $poligonlar)
            ->when($bbox, fn ($s) => $s->alanda($bbox))
            ->with(['alanlar' => fn ($s) => $s->whereIn('kira_dosya_id', $dosyaIdleri)->with([
                'isKolu:id,ad',
                'dosya:id,dosya_no,isyeri_adi,dolu_bos,ihale_turu,sozlesme_aktif_mi',
                'dosya.aktifSozlesme',
                'dosya.anaKiraci:id,kira_dosya_id,ad_unvan',
                'dosya.tasinmaz:id,kira_dosya_id,ilce_id,mahalle_id,ada,parsel,imar_durumu_id',
                'dosya.tasinmaz.ilce:id,ad', 'dosya.tasinmaz.mahalle:id,ad',
            ])])
            ->get();

        $features = [];
        foreach ($gorunen as $p) {
            $geo = $p->geoJson();
            $alanlar = $p->alanlar;
            if (! $geo || $alanlar->isEmpty()) {
                continue;
            }

            // Aynı poligonda birden çok kayıt olabildiği için öne çıkan kayıt
            // seçilir: önce dolu olan, yoksa ilk alan.
            $oneCikan = $alanlar->firstWhere('dolu_bos', KiraDoluBos::Dolu) ?? $alanlar->first();
            $d = $oneCikan->dosya;
            $t = $d?->tasinmaz;

            $features[] = [
                'type' => 'Feature',
                'id' => $p->id,
                'geometry' => $geo,
                'properties' => [
                    'poligon_id' => $p->id,
                    'id' => $d?->id,
                    'alan_id' => $oneCikan->id,
                    // Aynı poligonda kaç kayıt var — haritada rozet olarak gösterilir
                    'kayit_sayisi' => $alanlar->count(),
                    'dosya_no' => $d?->dosya_no,
                    'ada' => $t?->ada ?? $p->ada,
                    'parsel' => $t?->parsel ?? $p->parsel,
                    'ilce' => $t?->ilce?->ad,
                    'mahalle' => $t?->mahalle?->ad,
                    'alan' => $oneCikan->alan !== null ? (float) $oneCikan->alan : null,
                    'imar_id' => $t?->imar_durumu_id,
                    'isyeri' => $oneCikan->ad ?: $d?->isyeri_adi,
                    'is_kolu' => $oneCikan->isKolu?->ad,
                    'kiraci' => $oneCikan->kiraci_ad_unvan ?: $d?->anaKiraci?->ad_unvan,
                    'dolu_bos' => $oneCikan->dolu_bos->value,
                    'ihale' => $d?->ihale_turu->etiket(),
                    // Sözleşme bitişine göre renklendirme istemcide yapılır.
                    // Yürürlükte olmayan dosya süre uyarısı almaz.
                    'kalan_gun' => $oneCikan->kalanGun()
                        ?? ($d?->sozlesme_aktif_mi ? $d->aktifSozlesme?->kalanGun() : null),
                ],
            ];
        }

        return response()->json(['type' => 'FeatureCollection', 'kumeli' => false, 'toplam' => count($features), 'features' => $features]);
    }

    /** Harita yan paneli: dosyanın özeti, sözleşmesi ve bedeli. */
    public function haritaDosya(int $dosyaId): JsonResponse
    {
        $d = KiraDosyasi::with(['anaKiraci', 'isKolu', 'tasinmaz.ilce', 'tasinmaz.mahalle', 'tasinmaz.imarDurumu', 'aktifSozlesme.bedeller'])
            ->findOrFail($dosyaId);
        $t = $d->tasinmaz;
        $s = $d->aktifSozlesme;
        $bedel = $s?->guncelBedel();

        return response()->json([
            'id' => $d->id,
            'dosya_no' => $d->dosya_no,
            'isyeri' => $d->isyeri_adi,
            'is_kolu' => $d->isKolu?->ad,
            'kiraci' => $d->anaKiraci?->ad_unvan,
            'telefon' => $d->anaKiraci?->telefon,
            'dolu_bos' => $d->dolu_bos->etiket(),
            'ihale' => $d->ihale_turu->etiket(),
            'ilce' => $t?->ilce?->ad,
            'mahalle' => $t?->mahalle?->ad,
            'ada_parsel' => $t?->adaParsel(),
            'alan' => $t?->yuzolcum !== null ? (float) $t->yuzolcum : null,
            'imar' => $t?->imarDurumu?->ad,
            'adres' => $t?->adres,
            'baslangic' => $s?->baslangic_tarihi?->toDateString(),
            'bitis' => $s?->bitis_tarihi?->toDateString(),
            'kalan_gun' => $d->sozlesme_aktif_mi ? $s?->kalanGun() : null,
            'yillik_bedel' => $bedel?->yillik_bedel !== null ? (float) $bedel->yillik_bedel : null,
            'detay' => route('panel.kira.detay', $d->id),
            'liste' => route('panel.kira.index', ['q' => $d->dosya_no]),
        ]);
    }

    public function olustur(Request $request): View
    {
        return view('panel.kira.form', $this->formVerisi(null, $request));
    }

    /**
     * Yeni dosya: dosya + kiracı + taşınmaz + sözleşme + bedeller tek işlemde
     * yazılır. Parçalardan biri düşerse yarım dosya kalmasın diye hepsi aynı
     * veritabanı işleminde.
     */
    public function kaydet(Request $request): RedirectResponse
    {
        $veri = $this->dogrula($request);

        $dosya = DB::transaction(function () use ($veri, $request) {
            $dosya = KiraDosyasi::create([
                'dosya_no' => $veri['dosya_no'],
                'eski_dosya_no' => $veri['eski_dosya_no'] ?? null,
                'ana_dosya_id' => $veri['ana_dosya_id'] ?? null,
                'isyeri_adi' => $veri['isyeri_adi'] ?? null,
                'is_kolu_id' => $veri['is_kolu_id'] ?? null,
                'dolu_bos' => $veri['dolu_bos'],
                'ihale_turu' => $veri['ihale_turu'],
                'hukuki_durum_id' => $veri['hukuki_durum_id'] ?? null,
                'kiracilik_durum_id' => $veri['kiracilik_durum_id'] ?? null,
                'muhasebe_niteligi_id' => $veri['muhasebe_niteligi_id'] ?? null,
                'sozlesme_aktif_mi' => $request->boolean('sozlesme_aktif_mi'),
                'tahliye_ihbarnamesi_mi' => $request->boolean('tahliye_ihbarnamesi_mi'),
                'arac_kapasitesi' => $veri['arac_kapasitesi'] ?? null,
                'uyari' => $veri['uyari'] ?? null,
                'aciklama' => $veri['aciklama'] ?? null,
                'sorumlu_personel_id' => $veri['sorumlu_personel_id'] ?? null,
                'olusturan_id' => $request->user()->id,
            ]);

            if (filled($veri['kiraci_ad_unvan'] ?? null)) {
                $dosya->kiracilar()->create([
                    'tip' => 'ana',
                    'ad_unvan' => $veri['kiraci_ad_unvan'],
                    'tc_vergi_no' => $veri['kiraci_tc_vergi_no'] ?? null,
                    'telefon' => $veri['kiraci_telefon'] ?? null,
                    'eposta' => $veri['kiraci_eposta'] ?? null,
                    'adres' => $veri['kiraci_adres'] ?? null,
                    'iban' => $veri['kiraci_iban'] ?? null,
                    'avukat' => $veri['kiraci_avukat'] ?? null,
                ]);
            }

            $dosya->tasinmaz()->create([
                'tasinmaz_id' => $veri['tasinmaz_id'] ?? null,
                'il_id' => $veri['il_id'] ?? null,
                'ilce_id' => $veri['ilce_id'] ?? null,
                'mahalle_id' => $veri['mahalle_id'] ?? null,
                'ada' => $veri['ada'] ?? null,
                'parsel' => $veri['parsel'] ?? null,
                'adres' => $veri['adres'] ?? null,
                'yuzolcum' => $veri['yuzolcum'] ?? null,
                'imar_durumu_id' => $veri['imar_durumu_id'] ?? null,
            ]);

            $sozlesme = $dosya->sozlesmeler()->create([
                'sozlesme_no' => $veri['sozlesme_no'] ?? null,
                'baslangic_tarihi' => $veri['baslangic_tarihi'],
                'bitis_tarihi' => $veri['bitis_tarihi'],
                'sure_ay' => $veri['sure_ay'] ?? null,
                'taksit_sayisi' => $veri['taksit_sayisi'] ?? null,
                'odeme_sekli' => $veri['odeme_sekli'] ?? null,
                'sozlesme_tarihi' => $veri['sozlesme_tarihi'] ?? null,
                'noter_tarihi' => $veri['noter_tarihi'] ?? null,
                'yevmiye_no' => $veri['yevmiye_no'] ?? null,
                'kdv_orani' => $veri['kdv_orani'] ?? null,
                'kdv_dahil_mi' => $request->boolean('kdv_dahil_mi'),
                'artirma_yontemi' => $veri['artirma_yontemi'] ?? null,
                'aktif_mi' => true,
            ]);

            foreach ($this->bedelSatirlari($veri['bedeller'] ?? [], $veri['baslangic_tarihi'], $veri['bitis_tarihi']) as $satir) {
                $sozlesme->bedeller()->create($satir);
            }

            // Dosyanın birincil kira alanı; geometri varsa paylaşılabilir
            // poligona bağlanır (aynı poligon başka kayıtlarca da kullanılabilir).
            $dosya->alanlar()->create([
                'poligon_id' => $this->poligonuCozumle($veri, $request),
                'ad' => $veri['isyeri_adi'] ?? 'Ana alan',
                'adres' => $veri['adres'] ?? null,
                'alan' => $veri['yuzolcum'] ?? null,
                'is_kolu_id' => $veri['is_kolu_id'] ?? null,
                'dolu_bos' => $veri['dolu_bos'],
                'kiraci_ad_unvan' => $veri['kiraci_ad_unvan'] ?? null,
                'yillik_bedel' => $this->bedelSatirlari($veri['bedeller'] ?? [], $veri['baslangic_tarihi'], $veri['bitis_tarihi'])[0]['yillik_bedel'] ?? null,
                'baslangic_tarihi' => $veri['baslangic_tarihi'],
                'bitis_tarihi' => $veri['bitis_tarihi'],
                'aktif_mi' => true,
            ]);

            return $dosya;
        });

        return redirect()->route('panel.kira.index')
            ->with('basari', $dosya->dosya_no.' numaralı kira dosyası oluşturuldu.');
    }

    /**
     * Haritadan gelen parseli form alanlarına çevirir: TKGM mahalle kimliği
     * yerel il/ilçe/mahalleye çözülür, geometri ve alan TKGM'den alınır.
     * Referans sistemde yalnız geometri haritadan geliyordu; ada/parsel ve
     * alan elle giriliyordu.
     *
     * @return array<string, mixed>
     */
    private function onDolgu(Request $request): array
    {
        $dolgu = array_filter([
            'ada' => trim((string) $request->query('ada', '')),
            'parsel' => trim((string) $request->query('parsel', '')),
        ], fn ($v) => $v !== '');

        $mahalle = $request->query('mahalle_tkgm')
            ? Mahalle::with('ilce')->where('tkgm_id', $request->query('mahalle_tkgm'))->first()
            : null;

        if ($mahalle) {
            $dolgu['mahalle_id'] = $mahalle->id;
            $dolgu['ilce_id'] = $mahalle->ilce_id;
            $dolgu['il_id'] = $mahalle->ilce?->il_id;
            $dolgu['mahalle_ad'] = $mahalle->ad;
            $dolgu['ilce_ad'] = $mahalle->ilce?->ad;
        }

        // Geometri ve resmî alan TKGM'den; ağ hatası formu engellemez
        if ($mahalle && ($dolgu['ada'] ?? '') !== '' && ($dolgu['parsel'] ?? '') !== '') {
            try {
                $yanit = Http::timeout(10)
                    ->withHeaders(['Accept' => 'application/json', 'User-Agent' => 'EmlakYonetim/1.0'])
                    ->get("https://cbsapi.tkgm.gov.tr/megsiswebapi.v3.1/api/parsel/{$mahalle->tkgm_id}/{$dolgu['ada']}/{$dolgu['parsel']}");
                $veri = $yanit->successful() ? $yanit->json() : null;
                if (is_array($veri) && ! empty($veri['geometry'])) {
                    $dolgu['koordinat'] = json_encode($veri['geometry']);
                    $dolgu['yuzolcum'] = $this->tkgmSayisi($veri['properties']['alan'] ?? null);
                    $dolgu['nitelik'] = $veri['properties']['nitelik'] ?? null;
                }
            } catch (\Throwable $e) {
                // Geometri sonradan haritadan eklenebilir
            }
        }

        return array_filter($dolgu, fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Kaydın bağlanacağı poligonu bulur.
     *
     * Öncelik sırası: formda seçilen mevcut poligon → aynı parselin daha önce
     * çizilmiş poligonu → haritadan gelen yeni geometriden üretilen poligon.
     * Aynı parsel için ikinci bir poligon açılmaz; aynı poligonda birden çok
     * kayıt olabilmesinin temeli bu.
     *
     * @param  array<string, mixed>  $veri
     */
    private function poligonuCozumle(array $veri, Request $request): ?int
    {
        if (filled($veri['poligon_id'] ?? null)) {
            return (int) $veri['poligon_id'];
        }

        $mahalleId = $veri['mahalle_id'] ?? null;
        $ada = $veri['ada'] ?? null;
        $parsel = $veri['parsel'] ?? null;

        if ($mahalleId && $ada && $parsel) {
            $mevcut = KiraPoligon::parselde($mahalleId, $ada, $parsel)->first();
            if ($mevcut) {
                return $mevcut->id;
            }
        }

        $geometri = json_decode((string) ($veri['koordinat'] ?? ''), true);
        if (! is_array($geometri) || ! isset($geometri['type'])) {
            return null;
        }

        return KiraPoligon::create([
            'ad' => $veri['isyeri_adi'] ?? null,
            'kaynak' => 'tkgm',
            'mahalle_id' => $mahalleId,
            'ada' => $ada,
            'parsel' => $parsel,
            'koordinat' => $geometri,
            'alan' => $veri['yuzolcum'] ?? null,
            'olusturan_id' => $request->user()->id,
        ])->id;
    }

    /** TKGM sayıları Türkçe biçimde gelir: "618.795,00" → 618795.0 */
    private function tkgmSayisi(mixed $deger): ?float
    {
        $ham = trim((string) $deger);
        if ($ham === '') {
            return null;
        }
        $nokta = strrpos($ham, '.');
        $virgul = strrpos($ham, ',');

        if ($nokta !== false && $virgul !== false) {
            $sayi = $virgul > $nokta
                ? str_replace(',', '.', str_replace('.', '', $ham))
                : str_replace(',', '', $ham);
        } elseif ($virgul !== false) {
            $sayi = str_replace(',', '.', $ham);
        } else {
            $sayi = $ham;
        }

        return is_numeric($sayi) ? (float) $sayi : null;
    }

    public function duzenle(int $id): View
    {
        $dosya = KiraDosyasi::with(['kiracilar', 'tasinmaz', 'koordinat', 'sozlesmeler.bedeller'])->findOrFail($id);

        return view('panel.kira.form', $this->formVerisi($dosya, request()));
    }

    /**
     * Düzenleme. Dosyanın parçaları ayrı tablolarda olduğu için hepsi tek
     * işlemde güncellenir; bedeller yeniden yazılır (kullanıcı aradan satır
     * silmiş ya da yıl eklemiş olabilir).
     */
    public function guncelle(Request $request, int $id): RedirectResponse
    {
        $dosya = KiraDosyasi::with(['anaKiraci', 'tasinmaz', 'koordinat', 'sozlesmeler'])->findOrFail($id);
        $veri = $this->dogrula($request, $dosya);

        DB::transaction(function () use ($dosya, $veri, $request) {
            $dosya->update([
                'dosya_no' => $veri['dosya_no'],
                'eski_dosya_no' => $veri['eski_dosya_no'] ?? null,
                'ana_dosya_id' => $veri['ana_dosya_id'] ?? null,
                'isyeri_adi' => $veri['isyeri_adi'] ?? null,
                'is_kolu_id' => $veri['is_kolu_id'] ?? null,
                'dolu_bos' => $veri['dolu_bos'],
                'ihale_turu' => $veri['ihale_turu'],
                'hukuki_durum_id' => $veri['hukuki_durum_id'] ?? null,
                'kiracilik_durum_id' => $veri['kiracilik_durum_id'] ?? null,
                'muhasebe_niteligi_id' => $veri['muhasebe_niteligi_id'] ?? null,
                'sozlesme_aktif_mi' => $request->boolean('sozlesme_aktif_mi'),
                'tahliye_ihbarnamesi_mi' => $request->boolean('tahliye_ihbarnamesi_mi'),
                'arac_kapasitesi' => $veri['arac_kapasitesi'] ?? null,
                'uyari' => $veri['uyari'] ?? null,
                'aciklama' => $veri['aciklama'] ?? null,
                'sorumlu_personel_id' => $veri['sorumlu_personel_id'] ?? null,
            ]);

            $kiraciAlanlari = [
                'ad_unvan' => $veri['kiraci_ad_unvan'] ?? null,
                'tc_vergi_no' => $veri['kiraci_tc_vergi_no'] ?? null,
                'telefon' => $veri['kiraci_telefon'] ?? null,
                'eposta' => $veri['kiraci_eposta'] ?? null,
                'adres' => $veri['kiraci_adres'] ?? null,
                'iban' => $veri['kiraci_iban'] ?? null,
                'avukat' => $veri['kiraci_avukat'] ?? null,
            ];
            if (filled($kiraciAlanlari['ad_unvan'])) {
                $dosya->kiracilar()->updateOrCreate(['tip' => 'ana'], $kiraciAlanlari);
            } else {
                // Boş yere düşen dosyada ana kiracı satırı kalmasın
                $dosya->kiracilar()->where('tip', 'ana')->delete();
            }

            $dosya->tasinmaz()->updateOrCreate(['kira_dosya_id' => $dosya->id], [
                'tasinmaz_id' => $veri['tasinmaz_id'] ?? null,
                'il_id' => $veri['il_id'] ?? null,
                'ilce_id' => $veri['ilce_id'] ?? null,
                'mahalle_id' => $veri['mahalle_id'] ?? null,
                'ada' => $veri['ada'] ?? null,
                'parsel' => $veri['parsel'] ?? null,
                'adres' => $veri['adres'] ?? null,
                'yuzolcum' => $veri['yuzolcum'] ?? null,
                'imar_durumu_id' => $veri['imar_durumu_id'] ?? null,
            ]);

            $sozlesmeAlanlari = [
                'sozlesme_no' => $veri['sozlesme_no'] ?? null,
                'baslangic_tarihi' => $veri['baslangic_tarihi'],
                'bitis_tarihi' => $veri['bitis_tarihi'],
                'sure_ay' => $veri['sure_ay'] ?? null,
                'taksit_sayisi' => $veri['taksit_sayisi'] ?? null,
                'odeme_sekli' => $veri['odeme_sekli'] ?? null,
                'sozlesme_tarihi' => $veri['sozlesme_tarihi'] ?? null,
                'noter_tarihi' => $veri['noter_tarihi'] ?? null,
                'yevmiye_no' => $veri['yevmiye_no'] ?? null,
                'kdv_orani' => $veri['kdv_orani'] ?? null,
                'kdv_dahil_mi' => $request->boolean('kdv_dahil_mi'),
                'artirma_yontemi' => $veri['artirma_yontemi'] ?? null,
                'aktif_mi' => true,
            ];
            $sozlesme = $dosya->sozlesmeler()->where('aktif_mi', true)->latest('bitis_tarihi')->first();
            if ($sozlesme) {
                $sozlesme->update($sozlesmeAlanlari);
            } else {
                $sozlesme = $dosya->sozlesmeler()->create($sozlesmeAlanlari);
            }

            $sozlesme->bedeller()->delete();
            foreach ($this->bedelSatirlari($veri['bedeller'] ?? [], $veri['baslangic_tarihi'], $veri['bitis_tarihi']) as $satir) {
                $sozlesme->bedeller()->create($satir);
            }

            // Birincil alan güncellenir; yeni geometri geldiyse poligonu değişir
            $poligonId = $this->poligonuCozumle($veri, $request);
            $birincil = $dosya->alanlar()->orderBy('id')->first();
            $alanAlanlari = [
                'ad' => $veri['isyeri_adi'] ?? 'Ana alan',
                'adres' => $veri['adres'] ?? null,
                'alan' => $veri['yuzolcum'] ?? null,
                'is_kolu_id' => $veri['is_kolu_id'] ?? null,
                'dolu_bos' => $veri['dolu_bos'],
                'kiraci_ad_unvan' => $veri['kiraci_ad_unvan'] ?? null,
                'baslangic_tarihi' => $veri['baslangic_tarihi'],
                'bitis_tarihi' => $veri['bitis_tarihi'],
            ];
            if ($poligonId) {
                $alanAlanlari['poligon_id'] = $poligonId;
            }

            if ($birincil) {
                $birincil->update($alanAlanlari);
            } else {
                $dosya->alanlar()->create($alanAlanlari + ['aktif_mi' => true]);
            }
        });

        return redirect()->route('panel.kira.detay', $dosya->id)
            ->with('basari', $dosya->dosya_no.' numaralı dosya güncellendi.');
    }

    /**
     * Form alanlarının başlangıç değerleri. Sırayla: düzenlenen kaydın kendi
     * değeri → haritadan gelen ön dolgu → sabit varsayılan. Görünüm yalnızca
     * `old(...)` ile bu diziyi okur, kayıt/ekleme ayrımını bilmez.
     *
     * @param  array<string, mixed>  $onDolgu
     * @return array<string, mixed>
     */
    private function formVarsayilanlari(?KiraDosyasi $dosya, array $onDolgu): array
    {
        $t = $dosya?->tasinmaz;
        $kiraci = $dosya?->kiracilar->firstWhere('tip', KiraciTipi::Ana);
        $s = $dosya?->sozlesmeler->firstWhere('aktif_mi', true) ?? $dosya?->sozlesmeler->last();

        $bedeller = $s?->bedeller->map(fn ($b) => [
            'yillik_bedel' => $b->yillik_bedel,
            'donem_baslangic' => $b->donem_baslangic?->format('Y-m-d'),
            'donem_bitis' => $b->donem_bitis?->format('Y-m-d'),
            'tufe_orani' => $b->tufe_orani,
        ])->all();

        return [
            'dosya_no' => $dosya?->dosya_no,
            'eski_dosya_no' => $dosya?->eski_dosya_no,
            'ana_dosya_id' => $dosya?->ana_dosya_id,
            'isyeri_adi' => $dosya?->isyeri_adi,
            'is_kolu_id' => $dosya?->is_kolu_id,
            'arac_kapasitesi' => $dosya?->arac_kapasitesi,
            'dolu_bos' => $dosya?->dolu_bos?->value ?? 'dolu',
            'ihale_turu' => $dosya?->ihale_turu?->value ?? 'ihalesiz',
            'hukuki_durum_id' => $dosya?->hukuki_durum_id,
            'kiracilik_durum_id' => $dosya?->kiracilik_durum_id,
            'muhasebe_niteligi_id' => $dosya?->muhasebe_niteligi_id,
            'sozlesme_aktif_mi' => $dosya ? $dosya->sozlesme_aktif_mi : true,
            'tahliye_ihbarnamesi_mi' => $dosya?->tahliye_ihbarnamesi_mi ?? false,
            'uyari' => $dosya?->uyari,
            'aciklama' => $dosya?->aciklama,
            'sorumlu_personel_id' => $dosya?->sorumlu_personel_id,

            'kiraci_ad_unvan' => $kiraci?->ad_unvan,
            'kiraci_tc_vergi_no' => $kiraci?->tc_vergi_no,
            'kiraci_telefon' => $kiraci?->telefon,
            'kiraci_eposta' => $kiraci?->eposta,
            'kiraci_adres' => $kiraci?->adres,
            'kiraci_iban' => $kiraci?->iban,
            'kiraci_avukat' => $kiraci?->avukat,

            'tasinmaz_id' => $t?->tasinmaz_id,
            'il_id' => $t?->il_id ?? ($onDolgu['il_id'] ?? null),
            'ilce_id' => $t?->ilce_id ?? ($onDolgu['ilce_id'] ?? null),
            'mahalle_id' => $t?->mahalle_id ?? ($onDolgu['mahalle_id'] ?? null),
            'ada' => $t?->ada ?? ($onDolgu['ada'] ?? null),
            'parsel' => $t?->parsel ?? ($onDolgu['parsel'] ?? null),
            'adres' => $t?->adres,
            'yuzolcum' => $t?->yuzolcum ?? ($onDolgu['yuzolcum'] ?? null),
            'imar_durumu_id' => $t?->imar_durumu_id,
            'koordinat' => $onDolgu['koordinat'] ?? '',

            'sozlesme_no' => $s?->sozlesme_no,
            'baslangic_tarihi' => $s?->baslangic_tarihi?->format('Y-m-d'),
            'bitis_tarihi' => $s?->bitis_tarihi?->format('Y-m-d'),
            'sure_ay' => $s?->sure_ay,
            'taksit_sayisi' => $s?->taksit_sayisi ?? 12,
            'odeme_sekli' => $s?->odeme_sekli,
            'sozlesme_tarihi' => $s?->sozlesme_tarihi?->format('Y-m-d'),
            'noter_tarihi' => $s?->noter_tarihi?->format('Y-m-d'),
            'yevmiye_no' => $s?->yevmiye_no,
            'kdv_orani' => $s?->kdv_orani,
            'kdv_dahil_mi' => $s?->kdv_dahil_mi ?? false,
            'artirma_yontemi' => $s?->artirma_yontemi ?? 'TÜFE',

            'bedeller' => $bedeller ?: [['yillik_bedel' => '', 'donem_baslangic' => '', 'donem_bitis' => '', 'tufe_orani' => '']],
        ];
    }

    /** @return array<string, mixed> */
    private function formVerisi(?KiraDosyasi $dosya, Request $request): array
    {
        $onDolgu = $dosya ? [] : $this->onDolgu($request);
        $v = $this->formVarsayilanlari($dosya, $onDolgu);
        $ilId = old('il_id', $v['il_id'] ?? $request->query('il_id'));
        $ilceId = old('ilce_id', $v['ilce_id'] ?? $request->query('ilce_id'));

        return [
            'dosya' => $dosya,
            'onDolgu' => $onDolgu,
            'v' => $v,
            'iller' => Il::orderBy('ad')->get(['id', 'ad']),
            'ilceler' => $ilId ? Ilce::where('il_id', $ilId)->orderBy('ad')->get(['id', 'ad']) : collect(),
            'mahalleler' => $ilceId ? Mahalle::where('ilce_id', $ilceId)->orderBy('ad')->get(['id', 'ad']) : collect(),
            'isKollari' => KiraIsKolu::aktif()->get(['id', 'ad']),
            'hukukiDurumlar' => KiraHukukiDurum::aktif()->get(['id', 'ad']),
            'kiracilikDurumlar' => KiraKiracilikDurum::aktif()->get(['id', 'ad']),
            'muhasebeNitelikleri' => KiraMuhasebeNiteligi::aktif()->get(['id', 'ad']),
            'imarDurumlari' => ImarDurumu::where('aktif_mi', true)->orderBy('sira')->orderBy('ad')->get(['id', 'ad']),
            'anaDosyalar' => KiraDosyasi::whereNull('ana_dosya_id')->orderBy('dosya_no')->limit(500)->get(['id', 'dosya_no', 'isyeri_adi']),
        ];
    }

    /** @return array<string, mixed> */
    private function dogrula(Request $request, ?KiraDosyasi $dosya = null): array
    {
        // İlçe seçilen ile, mahalle seçilen ilçeye ait olmalı
        $ilceKurali = Rule::exists('ilceler', 'id');
        if ($request->filled('il_id')) {
            $ilceKurali->where('il_id', $request->integer('il_id'));
        }
        $mahalleKurali = Rule::exists('mahalleler', 'id');
        if ($request->filled('ilce_id')) {
            $mahalleKurali->where('ilce_id', $request->integer('ilce_id'));
        }

        return $request->validate([
            // Dosya
            'dosya_no' => ['required', 'string', 'max:50', Rule::unique('kira_dosyalari', 'dosya_no')->ignore($dosya?->id)],
            'eski_dosya_no' => ['nullable', 'string', 'max:50'],
            'ana_dosya_id' => ['nullable', 'exists:kira_dosyalari,id'],
            'isyeri_adi' => ['nullable', 'string', 'max:200'],
            'is_kolu_id' => ['nullable', 'exists:kira_is_kollari,id'],
            'dolu_bos' => ['required', Rule::in(['dolu', 'bos'])],
            'ihale_turu' => ['required', Rule::in(['ihaleli', 'ihalesiz'])],
            'arac_kapasitesi' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'hukuki_durum_id' => ['nullable', 'exists:kira_hukuki_durumlar,id'],
            'kiracilik_durum_id' => ['nullable', 'exists:kira_kiracilik_durumlari,id'],
            'muhasebe_niteligi_id' => ['nullable', 'exists:kira_muhasebe_nitelikleri,id'],
            'sorumlu_personel_id' => ['nullable', 'exists:kullanicilar,id'],
            'uyari' => ['nullable', 'string', 'max:300'],
            'aciklama' => ['nullable', 'string', 'max:5000'],

            // Kiracı (boş bırakılabilir: boş yerlerde kiracı yok)
            'kiraci_ad_unvan' => ['nullable', 'string', 'max:250', 'required_if:dolu_bos,dolu'],
            'kiraci_tc_vergi_no' => ['nullable', 'string', 'max:20'],
            'kiraci_telefon' => ['nullable', 'string', 'max:30'],
            'kiraci_eposta' => ['nullable', 'email', 'max:150'],
            'kiraci_adres' => ['nullable', 'string', 'max:1000'],
            'kiraci_iban' => ['nullable', 'string', 'max:34'],
            'kiraci_avukat' => ['nullable', 'string', 'max:200'],

            // Taşınmaz
            'tasinmaz_id' => ['nullable', 'exists:tasinmazlar,id'],
            'il_id' => ['nullable', 'exists:iller,id'],
            'ilce_id' => ['nullable', $ilceKurali],
            'mahalle_id' => ['nullable', $mahalleKurali],
            'ada' => ['nullable', 'string', 'max:20'],
            'parsel' => ['nullable', 'string', 'max:20'],
            'adres' => ['nullable', 'string', 'max:1000'],
            'yuzolcum' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'imar_durumu_id' => ['nullable', 'exists:imar_durumlari,id'],
            'koordinat' => ['nullable', 'string', 'max:200000'],
            'poligon_id' => ['nullable', 'exists:kira_poligonlari,id'],

            // Sözleşme
            'sozlesme_no' => ['nullable', 'string', 'max:60'],
            'baslangic_tarihi' => ['required', 'date'],
            'bitis_tarihi' => ['required', 'date', 'after:baslangic_tarihi'],
            'sure_ay' => ['nullable', 'integer', 'min:1', 'max:1200'],
            'taksit_sayisi' => ['nullable', 'integer', 'min:1', 'max:255'],
            'odeme_sekli' => ['nullable', 'string', 'max:80'],
            'sozlesme_tarihi' => ['nullable', 'date'],
            'noter_tarihi' => ['nullable', 'date'],
            'yevmiye_no' => ['nullable', 'string', 'max:60'],
            'kdv_orani' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'artirma_yontemi' => ['nullable', 'string', 'max:100'],

            // Bedeller
            'bedeller' => ['required', 'array', 'min:1', 'max:30'],
            'bedeller.*.yillik_bedel' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
            // Dönem ya iki tarihiyle girilir ya hiç girilmez; boşsa sözleşmeden hesaplanır
            'bedeller.*.donem_baslangic' => ['nullable', 'date', 'required_with:bedeller.*.donem_bitis'],
            'bedeller.*.donem_bitis' => ['nullable', 'date', 'required_with:bedeller.*.donem_baslangic', 'after_or_equal:bedeller.*.donem_baslangic'],
            'bedeller.*.tufe_orani' => ['nullable', 'numeric', 'min:-100', 'max:999'],
        ], [
            'bitis_tarihi.after' => 'Kira bitiş tarihi başlangıçtan sonra olmalı.',
            'kiraci_ad_unvan.required_if' => 'Dolu bir yerde kiracı adı/unvanı girilmeli.',
            'bedeller.required' => 'En az bir yıl için kira bedeli girilmeli.',
            'ilce_id.exists' => 'Seçilen ilçe bu ile ait değil.',
            'mahalle_id.exists' => 'Seçilen mahalle bu ilçeye ait değil.',
            'bedeller.*.donem_baslangic.required_with' => 'Bedel döneminin başlangıcı da girilmeli.',
            'bedeller.*.donem_bitis.required_with' => 'Bedel döneminin bitişi de girilmeli.',
            'bedeller.*.donem_bitis.after_or_equal' => 'Bedel döneminin bitişi başlangıçtan önce olamaz.',
        ]);
    }

    /**
     * Form satırlarını bedel kayıtlarına çevirir. Boş satırlar atılır, yıl
     * sırası baştan numaralanır — kullanıcı aradan satır silmiş olabilir.
     *
     * Dönemi girilmemiş satır sözleşmenin N. yılı sayılır. Dönemsiz bedel
     * yıllık toplama ve "güncel bedel"e hiç girmiyordu; sözleşme süresini
     * aşan satır dönemsiz kalır.
     *
     * @param  array<int, array<string, mixed>>  $satirlar
     * @return array<int, array<string, mixed>>
     */
    private function bedelSatirlari(array $satirlar, string $sozlesmeBaslangic, string $sozlesmeBitis): array
    {
        $dolu = array_values(array_filter($satirlar, fn ($s) => filled($s['yillik_bedel'] ?? null)));
        $ilk = Carbon::parse($sozlesmeBaslangic)->startOfDay();
        $son = Carbon::parse($sozlesmeBitis)->startOfDay();

        return array_map(function ($s, $i) use ($ilk, $son) {
            $baslangic = $s['donem_baslangic'] ?? null;
            $bitis = $s['donem_bitis'] ?? null;

            if (blank($baslangic) && blank($bitis)) {
                $yilBasi = $ilk->copy()->addYearsNoOverflow($i);
                if ($yilBasi->lte($son)) {
                    $baslangic = $yilBasi->toDateString();
                    $bitis = $yilBasi->copy()->addYearNoOverflow()->subDay()->min($son)->toDateString();
                }
            }

            return [
                'yil_sirasi' => $i + 1,
                'donem_baslangic' => $baslangic ?: null,
                'donem_bitis' => $bitis ?: null,
                'yillik_bedel' => $s['yillik_bedel'],
                'tufe_orani' => $s['tufe_orani'] ?? null,
            ];
        }, $dolu, array_keys($dolu));
    }

    /** Tabloda görünen kayıt sayısı. 10–200 arası; boş veya geçersiz değer 50. */
    private function sayfaBoyu(Request $request): int
    {
        $ham = $request->query('per_page', 50);
        $n = is_numeric($ham) ? (int) $ham : 50;

        return min(200, max(10, $n));
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
        // Yürürlükte olmayan dosyanın bedeli tahsil edilmez, toplama girmez
        $idler = (clone $taban)->where('sozlesme_aktif_mi', true)->select('id');

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
        $basliklar = ['Dosya No', 'Eski Dosya No', 'İşyeri Adı', 'Ana Kiracı', 'İş Kolu', 'İlçe', 'Mahalle', 'Ada', 'Parsel', 'Yüzölçüm (m²)', 'Durum', 'İhale', 'Sözleşme', 'Kira Başlangıç', 'Kira Bitiş', 'Güncel Yıllık Bedel (₺)', 'Adres'];
        $sh->fromArray($basliklar, null, 'A1');
        $son = 'Q';
        $sh->getStyle("A1:{$son}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0D9488']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);

        $r = 2;
        foreach ($kayitlar as $k) {
            $t = $k->tasinmaz;
            $s = $k->aktifSozlesme;
            $bedel = $s?->guncelBedel()?->yillik_bedel;
            $sh->fromArray([
                $k->dosya_no, $k->eski_dosya_no, $k->isyeri_adi,
                $k->anaKiraci?->ad_unvan, $k->isKolu?->ad,
                $t?->ilce?->ad, $t?->mahalle?->ad, $t?->ada, $t?->parsel,
                $t?->yuzolcum !== null ? (float) $t->yuzolcum : null,
                $k->dolu_bos->etiket(), $k->ihale_turu->etiket(),
                $k->sozlesme_aktif_mi ? 'Yürürlükte' : 'Yürürlükte değil',
                $s?->baslangic_tarihi?->format('d.m.Y'), $s?->bitis_tarihi?->format('d.m.Y'),
                $bedel !== null ? (float) $bedel : null,
                $t?->adres,
            ], null, 'A'.$r);
            $r++;
        }
        foreach (['J', 'P'] as $sayiKolonu) {
            $sh->getStyle($sayiKolonu.'2:'.$sayiKolonu.max(2, $r - 1))->getNumberFormat()->setFormatCode('#,##0.00');
        }
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
