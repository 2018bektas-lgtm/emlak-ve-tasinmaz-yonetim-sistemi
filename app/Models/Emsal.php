<?php

namespace App\Models;

use App\Enums\EmsalTipi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Karşılaştırılabilir satış kaydı. Tür (encümen / resmî senet / mahkeme
 * kararı) `tip` ile ayrılır; türe özel alanlar nullable tutulur, hangi türde
 * hangilerinin dolduğunu EmsalTipi::alanlar() söyler.
 */
class Emsal extends Model
{
    protected $table = 'emsaller';

    protected $fillable = [
        'emsal_parsel_id',
        'tip',
        'tarih',
        'birim_fiyat',
        'evrak',
        'aciklama',
        'karar_no',
        'kayit_no',
        'satilan_hisse',
        'yevmiye_no',
        'satis_durumu',
        'esas_no',
        'dava_tarihi',
        'olusturan_id',
    ];

    /** Parsel özeti (harita/liste bundan okur) emsal değiştikçe tazelenir. */
    protected static function booted(): void
    {
        $tazele = function (self $e) {
            $e->parsel?->ozetiTazele();
        };

        static::saved($tazele);
        static::deleted($tazele);
    }

    protected function casts(): array
    {
        return [
            'tip' => EmsalTipi::class,
            'tarih' => 'date',
            'dava_tarihi' => 'date',
            'birim_fiyat' => 'decimal:2',
        ];
    }

    public function parsel(): BelongsTo
    {
        return $this->belongsTo(EmsalParsel::class, 'emsal_parsel_id');
    }

    public function bagimsizBolumler(): HasMany
    {
        return $this->hasMany(EmsalBagimsizBolum::class, 'emsal_id');
    }

    public function olusturan(): BelongsTo
    {
        return $this->belongsTo(Kullanici::class, 'olusturan_id');
    }

    /** Türün kendi belge numarası — listede tek sütunda gösterilir. */
    public function belgeNo(): ?string
    {
        return match ($this->tip) {
            EmsalTipi::Encumen => $this->karar_no,
            EmsalTipi::Senet => $this->yevmiye_no,
            EmsalTipi::Mahkeme => $this->esas_no,
            default => null,
        };
    }

    /**
     * Belge numarası veya parsel bilgisiyle arama. Emsal başına EXISTS yerine
     * parseller bir kez süzülür — yüz binlerce satırda fark büyük.
     */
    public function scopeAra(Builder $q, ?string $arama): Builder
    {
        $arama = trim((string) $arama);
        if ($arama === '') {
            return $q;
        }

        // "1234/5" → ada 1234 + parsel 5
        $adaParsel = preg_match('~^\s*([\w-]+)\s*/\s*([\w-]+)\s*$~u', $arama, $m) ? [$m[1], $m[2]] : null;

        return $q->where(function (Builder $w) use ($arama, $adaParsel) {
            $w->where('karar_no', 'like', "%{$arama}%")
                ->orWhere('yevmiye_no', 'like', "%{$arama}%")
                ->orWhere('esas_no', 'like', "%{$arama}%")
                ->orWhere('kayit_no', 'like', "%{$arama}%")
                ->orWhereIn('emsal_parsel_id', EmsalParsel::query()->select('id')->where(function ($p) use ($arama, $adaParsel) {
                    $p->where('ada', $arama)
                        ->orWhere('parsel', $arama)
                        ->when($adaParsel, fn ($x) => $x->orWhere(fn ($y) => $y->where('ada', $adaParsel[0])->where('parsel', $adaParsel[1])))
                        ->orWhereIn('ilce_id', Ilce::query()->select('id')->where('ad', 'like', "%{$arama}%"))
                        ->orWhereIn('mahalle_id', Mahalle::query()->select('id')->where('ad', 'like', "%{$arama}%"));
                }));
        });
    }

    public function scopeTipi(Builder $q, ?string $tip): Builder
    {
        return $tip ? $q->where('tip', $tip) : $q;
    }

    /** @param  array{0: ?string, 1: ?string}  $aralik  [baslangic, bitis] */
    public function scopeTarihAraliginda(Builder $q, array $aralik): Builder
    {
        [$baslangic, $bitis] = $aralik;

        return $q->when($baslangic, fn (Builder $q) => $q->where('tarih', '>=', $baslangic))
            ->when($bitis, fn (Builder $q) => $q->where('tarih', '<=', $bitis));
    }
}
