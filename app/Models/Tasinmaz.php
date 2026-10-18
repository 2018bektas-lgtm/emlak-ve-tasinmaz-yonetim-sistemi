<?php

namespace App\Models;

use App\Enums\KayitTipi;
use App\Enums\SatisDurumu;
use App\Models\Concerns\PolimorfikSahipCascade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Tasinmaz extends Model
{
    use PolimorfikSahipCascade;

    protected $table = 'tasinmazlar';

    /**
     * Taşınmazın kimliği + kadastral bilgisi burada. Durum alanları
     * (muhasebe/kayıt türü, işgal/tahsis/kira/satış vb.) `tasinmaz_kategori`,
     * `tasinmaz_ekbilgi` ve bağımsız bölüm başına `tasinmaz_yapi` tablolarındadır.
     */
    protected $fillable = [
        'il_id',
        'ilce_id',
        'mahalle_id',
        'ada',
        'parsel',
        'alan',
        'nitelik',
        'uzeri_bina_var_mi',
        'kayit_tipi',
    ];

    protected function casts(): array
    {
        return [
            'alan' => 'decimal:2',
            'uzeri_bina_var_mi' => 'boolean',
            'kayit_tipi' => KayitTipi::class,
        ];
    }

    protected static function booted(): void
    {
        // mahalle_id set edildiginde il_id ve ilce_id otomatik doldurulur —
        // kullanicidan sadece mahalle_id istenebilir, denormalize kolonlar
        // tutarli kalir.
        static::saving(function (self $tasinmaz): void {
            if (! $tasinmaz->isDirty('mahalle_id') || $tasinmaz->mahalle_id === null) {
                return;
            }

            $mahalle = Mahalle::query()
                ->select('id', 'ilce_id')
                ->with('ilce:id,il_id')
                ->findOrFail($tasinmaz->mahalle_id);

            $tasinmaz->ilce_id = $mahalle->ilce_id;
            $tasinmaz->il_id = $mahalle->ilce->il_id;
        });

        // Yapıları model level'da sil ki her yapının `deleting` event'i
        // tetiklensin ve kendi polimorfik cocuklarini (tapu/karar/resim)
        // temizlesin. DB-level FK cascade tek basina model event'i
        // tetiklemez, bu yuzden manuel yapiyoruz.
        static::deleting(function (self $tasinmaz): void {
            // Son savunma hattı: bağlı hisse/lojman/işgal kaydı olan taşınmaz silinmez
            if ($engel = $tasinmaz->silmeEngelleri()) {
                throw new \DomainException('Taşınmaz silinemez: '.implode(', ', $engel).'.');
            }
            $tasinmaz->yapilar->each->delete();
        });

        // Lojmanların konum kopyası taşınmazdan gelir; taşınmaz düzeltilince eşitlenir
        static::updated(function (self $tasinmaz): void {
            if ($tasinmaz->wasChanged(['mahalle_id', 'ada', 'parsel', 'il_id', 'ilce_id'])) {
                Lojman::where('tasinmaz_id', $tasinmaz->id)->update([
                    'il_id' => $tasinmaz->il_id,
                    'ilce_id' => $tasinmaz->ilce_id,
                    'mahalle_id' => $tasinmaz->mahalle_id,
                    'ada' => $tasinmaz->ada,
                    'parsel' => $tasinmaz->parsel,
                ]);
            }
        });
    }

    // --- Lokasyon iliskileri ---

    public function il(): BelongsTo
    {
        return $this->belongsTo(Il::class, 'il_id');
    }

    public function ilce(): BelongsTo
    {
        return $this->belongsTo(Ilce::class, 'ilce_id');
    }

    public function mahalle(): BelongsTo
    {
        return $this->belongsTo(Mahalle::class, 'mahalle_id');
    }

    /**
     * mahalle.tkgm_id + ada + parsel (+ kat mülkiyetli kayıtlarda bölüm) ile
     * taşınmazı bulur. Aynı parselde birden çok bağımsız bölüm kaydı
     * olabildiği için bölüm parçası (ör. "A-10") kaydı tekilleştirir.
     * Bölüm verilmezse parsel kaydı (boş parsel / kat mülkiyetsiz bina)
     * tercih edilir; yoksa en eski kayıt döner (eski bağlantılar için).
     */
    public static function slugIleBul(int $mahalleTkgmId, string $ada, string $parsel, ?string $bolum = null): self
    {
        $adaylar = static::query()
            ->whereHas('mahalle', fn ($q) => $q->where('tkgm_id', $mahalleTkgmId))
            ->where('ada', $ada)
            ->where('parsel', $parsel)
            ->with('yapi')
            ->orderBy('id')
            ->get();

        $kayit = filled($bolum)
            ? $adaylar->first(fn (self $t) => $t->bolumSlug() === $bolum)
            : ($adaylar->first(fn (self $t) => $t->bolumSlug() === null) ?? $adaylar->first());

        if (! $kayit) {
            throw (new \Illuminate\Database\Eloquent\ModelNotFoundException)->setModel(static::class);
        }

        return $kayit;
    }

    /**
     * Kat mülkiyetli kayıtların adresindeki bölüm parçası: "A-10" (bloklu)
     * veya "10" (bloksuz). Diğer kayıt tiplerinde null.
     */
    public function bolumSlug(): ?string
    {
        if ($this->kayit_tipi !== KayitTipi::KatMulkiyetli) {
            return null;
        }

        $yapi = $this->relationLoaded('yapi')
            ? $this->yapi
            : ($this->relationLoaded('yapilar') ? $this->yapilar->first() : $this->yapi);

        $bbn = self::slugParcasi(\App\Support\TasinmazTekillik::numara($yapi?->bagimsiz_bolum_no));
        if ($bbn === '') {
            return null;
        }
        $blok = self::slugParcasi(\App\Support\TasinmazTekillik::blok($yapi?->blok_no));

        return $blok !== '' ? $blok.'-'.$bbn : $bbn;
    }

    private static function slugParcasi(string $deger): string
    {
        return preg_replace('/[^A-Za-z0-9]/', '', \Illuminate\Support\Str::ascii($deger)) ?? '';
    }

    /**
     * duzenle/guncelle/sil rotalarının parametreleri (mahalleTkgmId, ada, parsel
     * ve kat mülkiyetli kayıtlarda bolum). mahalle.tkgm_id / ada / parsel eksikse
     * fallback: id-tabanlı legacy rota.
     *
     * @return array{mahalleTkgmId:int|string, ada:string, parsel:string, bolum?:string}|array{tasinmaz:int}
     */
    public function duzenleParams(): array
    {
        $tkgm = $this->relationLoaded('mahalle')
            ? optional($this->mahalle)->tkgm_id
            : Mahalle::query()->whereKey($this->mahalle_id)->value('tkgm_id');

        if ($tkgm && filled($this->ada) && filled($this->parsel)) {
            $params = [
                'mahalleTkgmId' => (int) $tkgm,
                'ada' => (string) $this->ada,
                'parsel' => (string) $this->parsel,
            ];
            if ($bolum = $this->bolumSlug()) {
                $params['bolum'] = $bolum;
            }

            return $params;
        }

        return ['tasinmaz' => $this->id];
    }

    /**
     * Rota adını ("detay" | "duzenle" | "guncelle" | "sil" | "hisseler.store" …)
     * verildiğinde uygun URL'yi üretir. Kat mülkiyetli kayıtlar ".bb" ekli
     * bölüm rotasını, slug verisi eksik kayıtlar legacy id-tabanlı rotayı kullanır.
     */
    public function rota(string $ad, array $ek = []): string
    {
        $slugParams = $this->duzenleParams();
        $sonek = match (true) {
            isset($slugParams['tasinmaz']) => '-legacy',
            isset($slugParams['bolum']) => '.bb',
            default => '',
        };

        return route('panel.tasinmazlar.'.$ad.$sonek, array_merge($slugParams, $ek));
    }

    /**
     * TKGM Parsel Sorgu deep-link URL'i.
     * mahalle.tkgm_id + ada + parsel varsa döner, aksi halde null.
     */
    public function tkgmSorguUrl(): ?string
    {
        $tkgm = $this->relationLoaded('mahalle')
            ? optional($this->mahalle)->tkgm_id
            : Mahalle::query()->whereKey($this->mahalle_id)->value('tkgm_id');

        if (! $tkgm || ! filled($this->ada) || ! filled($this->parsel)) {
            return null;
        }

        return sprintf(
            'https://parselsorgu.tkgm.gov.tr/#ara/idari/%d/%s/%s',
            (int) $tkgm,
            rawurlencode((string) $this->ada),
            rawurlencode((string) $this->parsel),
        );
    }

    /**
     * Mülkiyet durumu — aktif hisselere göre "TAM" veya "HİSSELİ".
     * TAM: tek aktif hisse ve pay==payda (yani %100).
     * HİSSELİ: birden fazla aktif hisse veya oran < %100.
     * Aktif hisse yoksa null döner.
     */
    public function getMulkiyetDurumuAttribute(): ?string
    {
        $hisseler = $this->relationLoaded('hisseler')
            ? $this->hisseler
            : $this->hisseler()->get();

        $aktifler = $hisseler->where('hisse_durum', 'aktif');
        if ($aktifler->isEmpty()) {
            return null;
        }
        if ($aktifler->count() === 1) {
            $h = $aktifler->first();
            $pay = $h->hisse_pay !== null ? (float) $h->hisse_pay : null;
            $payda = $h->hisse_payda !== null ? (float) $h->hisse_payda : null;
            if ($pay !== null && $payda !== null && $payda != 0.0 && abs($pay - $payda) < 0.0001) {
                return 'TAM';
            }
        }

        return 'HİSSELİ';
    }

    // --- Sadece arsaya ozgu iliskiler ---

    public function koordinat(): HasOne
    {
        return $this->hasOne(TasinmazKoordinat::class, 'tasinmaz_id');
    }

    public function imar(): HasOne
    {
        return $this->hasOne(TasinmazImar::class, 'tasinmaz_id');
    }

    /**
     * Bu taşınmazın kategorisi (arsa seviyesi): muhasebe/kayıt/kullanım.
     */
    public function kategori(): HasOne
    {
        return $this->hasOne(TasinmazKategori::class, 'tasinmaz_id');
    }

    /**
     * Ek bilgi (arsa seviyesi): işgal/satış/tahsis/ust_hakki/kira/meclis/açıklama.
     */
    public function ekbilgi(): HasOne
    {
        return $this->hasOne(TasinmazEkbilgi::class, 'tasinmaz_id');
    }

    /**
     * Bağımsız bölümler (BBN): daire/dükkan/depo… her biri kendi durum
     * alanlarını taşır. Politika: her taşınmaz kaydı en fazla 1 BBN bilgisi
     * taşır (aynı ada/parselde ek meskenler ayrı taşınmaz kaydı olur).
     * `yapilar()` legacy amaçlı hasMany kalır; UI ve controller `yapi()`
     * hasOne üzerinden çalışır.
     */
    public function yapilar(): HasMany
    {
        return $this->hasMany(TasinmazYapi::class, 'tasinmaz_id')
            ->orderBy('sira')
            ->orderBy('id');
    }

    public function yapi(): HasOne
    {
        return $this->hasOne(TasinmazYapi::class, 'tasinmaz_id')->oldestOfMany();
    }

    public function hisseler(): HasMany
    {
        return $this->hasMany(TasinmazHisse::class, 'tasinmaz_id')->orderBy('sira')->orderBy('id');
    }

    /**
     * Tapudaki hissedarlar (hisse satış tebligatlarında çoklu seçim listesi).
     */
    public function malikler(): HasMany
    {
        return $this->hasMany(TasinmazMalik::class, 'tasinmaz_id')->orderBy('id');
    }

    /** Bu taşınmazdaki lojmanlar (lojman modülünde açılır; burada yalnız okunur). */
    public function lojmanlar(): HasMany
    {
        return $this->hasMany(Lojman::class, 'tasinmaz_id')->orderBy('blok')->orderBy('daire_no');
    }

    /** Bu taşınmaza bağlı ecrimisil (işgal) kayıtları — salt okunur. */
    public function ecrimisiller(): HasMany
    {
        return $this->hasMany(EcrimisilIsgalci::class, 'tasinmaz_id')->orderByDesc('id');
    }

    /** Değerleme raporları (arsa ve bağımsız bölüm) — salt okunur, en yeni önce. */
    public function degerlemeler(): HasMany
    {
        return $this->hasMany(TasinmazDegerleme::class, 'tasinmaz_id')->orderByDesc('rapor_tarihi')->orderByDesc('id');
    }

    /** Hisse satışı başvuruları — salt okunur. */
    public function hisseBasvurulari(): HasMany
    {
        return $this->hasMany(HisseBasvuru::class, 'tasinmaz_id')->orderByDesc('id');
    }

    /**
     * Taşınmaz silinirse kaybolacak/kopacak bağlı kayıtlar. Boşsa silinebilir.
     * (Hisse satış dosyaları taşınmazla birlikte zincirleme siliniyor; lojman
     * ve işgal kayıtları sessizce taşınmazsız kalıyordu.)
     *
     * @return array<int, string>
     */
    public function silmeEngelleri(): array
    {
        $engel = [];
        if (($n = $this->hisseBasvurulari()->count()) > 0) {
            $engel[] = "{$n} hisse satışı başvurusu (dosya geçmişi silinirdi)";
        }
        if (($n = $this->lojmanlar()->count()) > 0) {
            $engel[] = "{$n} lojman kaydı";
        }
        if (($n = $this->ecrimisiller()->count()) > 0) {
            $engel[] = "{$n} ecrimisil (işgal) kaydı";
        }
        if (($n = $this->degerlemeler()->count()) > 0) {
            $engel[] = "{$n} değerleme raporu";
        }

        return $engel;
    }

    /**
     * Hisse satış encümen kararları (grup_no üzerinden basvuru grubuna bağlanır).
     */
    public function encumenler(): HasMany
    {
        return $this->hasMany(TasinmazEncumen::class, 'tasinmaz_id')->orderByDesc('id');
    }

    /**
     * Satış durumu — her zaman ekbilgi'den (formda "Ek Bilgi" bölümünde girilen).
     * Yapı (BBN) satırlarındaki satis_durumu hiçbir ekranda düzenlenmez ve hep
     * varsayılan "envanterde" kalır; eskiden önce yapılara bakıldığı için BBN'li
     * kayıtlarda kullanıcının seçtiği durum görünmüyordu.
     */
    public function satisOzeti(): SatisDurumu
    {
        $ekbilgi = $this->relationLoaded('ekbilgi') ? $this->ekbilgi : $this->ekbilgi()->first();
        if (! $ekbilgi) {
            return SatisDurumu::Envanterde;
        }

        return $ekbilgi->satis_durumu instanceof SatisDurumu
            ? $ekbilgi->satis_durumu
            : (SatisDurumu::tryFrom((string) $ekbilgi->satis_durumu) ?? SatisDurumu::Envanterde);
    }

    // --- Polimorfik iliskiler (hem Tasinmaz hem TasinmazBirim'de ayni) ---

    public function tapu(): MorphOne
    {
        return $this->morphOne(Tapu::class, 'sahip');
    }

    public function meclisKararlari(): MorphMany
    {
        return $this->morphMany(MeclisKarari::class, 'sahip');
    }

    public function resimler(): MorphMany
    {
        return $this->morphMany(Resim::class, 'sahip');
    }
}
