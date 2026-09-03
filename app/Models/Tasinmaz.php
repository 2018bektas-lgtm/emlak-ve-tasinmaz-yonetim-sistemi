<?php

namespace App\Models;

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
     * Refactor sonrası taşınmazın kimliği + kadastral bilgisi burada.
     * Durum alanları (muhasebe/kayıt türü/işgal/tahsis/kira/satış vb.)
     * artık `tasinmaz_birimler` tablosunda birim başına tutulur.
     */
    protected $fillable = [
        'il_id',
        'ilce_id',
        'mahalle_id',
        'mudurluk_id',
        'ada',
        'parsel',
        'alan',
        'nitelik',
        'uzeri_bina_var_mi',
    ];

    protected function casts(): array
    {
        return [
            'alan' => 'decimal:2',
            'uzeri_bina_var_mi' => 'boolean',
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
            $tasinmaz->yapilar->each->delete();
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

    public function mudurluk(): BelongsTo
    {
        return $this->belongsTo(Mudurluk::class, 'mudurluk_id');
    }

    /**
     * Kullanıcının müdürlük kapsamına göre kayıtları daraltır.
     * tasinmaz.tumunu-gor izni olanlar (ve admin) tüm kayıtları görür.
     */
    public function scopeMudurlukKapsami($query, ?Kullanici $kullanici = null)
    {
        $kullanici ??= auth()->user();
        if (! $kullanici instanceof Kullanici || $kullanici->izinVarMi('tasinmaz.tumunu-gor')) {
            return $query;
        }

        if (! $kullanici->mudurluk_id) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where('mudurluk_id', $kullanici->mudurluk_id);
    }

    public function mudurlukErisilebilirMi(?Kullanici $kullanici = null): bool
    {
        $kullanici ??= auth()->user();
        if (! $kullanici instanceof Kullanici) {
            return false;
        }

        return $kullanici->tasinmazGorebilirMi($this);
    }

    /**
     * mahalle.tkgm_id + ada + parsel üçlüsü ile taşınmazı bulur.
     */
    public static function slugIleBul(int $mahalleTkgmId, string $ada, string $parsel): self
    {
        return static::query()
            ->whereHas('mahalle', fn ($q) => $q->where('tkgm_id', $mahalleTkgmId))
            ->where('ada', $ada)
            ->where('parsel', $parsel)
            ->orderBy('id')
            ->firstOrFail();
    }

    /**
     * duzenle/guncelle/sil rotalarının parametreleri (mahalleTkgmId, ada, parsel).
     * mahalle.tkgm_id / ada / parsel eksikse fallback: id-tabanlı legacy rota.
     *
     * @return array{mahalleTkgmId:int|string, ada:string, parsel:string}|array{tasinmaz:int}
     */
    public function duzenleParams(): array
    {
        $tkgm = $this->relationLoaded('mahalle')
            ? optional($this->mahalle)->tkgm_id
            : Mahalle::query()->whereKey($this->mahalle_id)->value('tkgm_id');

        if ($tkgm && filled($this->ada) && filled($this->parsel)) {
            return [
                'mahalleTkgmId' => (int) $tkgm,
                'ada' => (string) $this->ada,
                'parsel' => (string) $this->parsel,
            ];
        }

        return ['tasinmaz' => $this->id];
    }

    /**
     * Rota adını ("duzenle" | "guncelle" | "sil") verildiğinde uygun URL'yi üretir.
     * Slug verisi eksikse otomatik olarak legacy id-tabanlı rotayı kullanır.
     */
    public function rota(string $ad): string
    {
        $slugParams = $this->duzenleParams();
        $legacy = isset($slugParams['tasinmaz']);
        $baseName = 'panel.tasinmazlar.'.$ad.($legacy ? '-legacy' : '');

        return route($baseName, $slugParams);
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
     * alanlarını taşır; arsa üzerinde bina varsa 1+ satır olur.
     */
    public function yapilar(): HasMany
    {
        return $this->hasMany(TasinmazYapi::class, 'tasinmaz_id')
            ->orderBy('sira')
            ->orderBy('id');
    }

    public function hisseler(): HasMany
    {
        return $this->hasMany(TasinmazHisse::class, 'tasinmaz_id')->orderBy('sira')->orderBy('id');
    }

    /**
     * Satış durumu: yapı varsa yapıların öncelikli özeti, yoksa ekbilgi'nin
     * kendi satış_durumu değeri.
     */
    public function satisOzeti(): SatisDurumu
    {
        $yapilar = $this->relationLoaded('yapilar')
            ? $this->yapilar
            : $this->yapilar()->get();

        if ($yapilar->isNotEmpty()) {
            $durumlar = $yapilar->map(function ($y) {
                return $y->satis_durumu instanceof SatisDurumu
                    ? $y->satis_durumu
                    : (SatisDurumu::tryFrom((string) $y->satis_durumu) ?? SatisDurumu::Envanterde);
            })->all();

            return SatisDurumu::oncelikli(...$durumlar);
        }

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
