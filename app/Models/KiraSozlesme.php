<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Kira sözleşmesi. Bedeller yıl bazlı ayrı satırlarda (kira_bedelleri) durur;
 * süre ve bitişe kalan gün saklanmaz, tarihlerden hesaplanır.
 */
class KiraSozlesme extends Model
{
    protected $table = 'kira_sozlesmeleri';

    protected $fillable = [
        'kira_dosya_id',
        'sozlesme_no',
        'baslangic_tarihi',
        'bitis_tarihi',
        'sure_ay',
        'taksit_sayisi',
        'odeme_sekli',
        'odeme_zamani',
        'son_odeme_gunu',
        'sozlesme_tarihi',
        'noter_tarihi',
        'yevmiye_no',
        'kdv_orani',
        'kdv_dahil_mi',
        'artirma_yontemi',
        'artirma_ayi',
        'aktif_mi',
        'aciklama',
    ];

    protected function casts(): array
    {
        return [
            'baslangic_tarihi' => 'date',
            'bitis_tarihi' => 'date',
            'sozlesme_tarihi' => 'date',
            'noter_tarihi' => 'date',
            'kdv_orani' => 'decimal:2',
            'kdv_dahil_mi' => 'boolean',
            'aktif_mi' => 'boolean',
        ];
    }

    public function dosya(): BelongsTo
    {
        return $this->belongsTo(KiraDosyasi::class, 'kira_dosya_id');
    }

    public function bedeller(): HasMany
    {
        return $this->hasMany(KiraBedel::class, 'kira_sozlesme_id')->orderBy('yil_sirasi');
    }

    /** Bu dönemin kiracıları — dönem değişince kiracı da değişir. */
    public function kiracilar(): HasMany
    {
        return $this->hasMany(KiraKiraci::class, 'kira_sozlesme_id');
    }

    public function anaKiraci(): HasOne
    {
        return $this->hasOne(KiraKiraci::class, 'kira_sozlesme_id')->where('tip', 'ana');
    }

    public function belgeler(): HasMany
    {
        return $this->hasMany(KiraBelge::class, 'kira_sozlesme_id');
    }

    /** Bugün yürürlükte mi? Hem işaret hem tarih aralığı aranır. */
    public function yururlukteMi(): bool
    {
        if (! $this->aktif_mi) {
            return false;
        }
        $bugun = now()->startOfDay();

        return (! $this->baslangic_tarihi || $this->baslangic_tarihi->lte($bugun))
            && (! $this->bitis_tarihi || $this->bitis_tarihi->gte($bugun));
    }

    /** Yürürlükteki dönemin bedeli: bugünü kapsayan satır, yoksa sonuncusu. */
    public function guncelBedel(): ?KiraBedel
    {
        $bugun = now()->startOfDay();

        return $this->bedeller->first(fn (KiraBedel $b) => $b->donem_baslangic && $b->donem_bitis
            && $b->donem_baslangic->lte($bugun) && $b->donem_bitis->gte($bugun))
            ?? $this->bedeller->last();
    }

    /** Sözleşme süresi (yıl) — sure_ay girilmişse ondan, değilse tarihlerden. */
    public function sureYil(): ?float
    {
        if ($this->sure_ay) {
            return round($this->sure_ay / 12, 1);
        }
        if (! $this->baslangic_tarihi || ! $this->bitis_tarihi) {
            return null;
        }

        return round($this->baslangic_tarihi->diffInDays($this->bitis_tarihi) / 365, 1);
    }

    /** Bitişe kalan gün; geçmişse negatif. */
    public function kalanGun(): ?int
    {
        // Sorgu `kalan_gun`'u seçtiyse onu kullan: Carbon'un diffInDays'i
        // pahalıdır ve harita binlerce kayıt için bunu çağırıyor. SQL'deki
        // `bitis_tarihi - CURRENT_DATE` bu hesabın aynısını verir.
        if (array_key_exists('kalan_gun', $this->attributes)) {
            return $this->attributes['kalan_gun'] === null
                ? null
                : (int) $this->attributes['kalan_gun'];
        }

        return $this->bitis_tarihi
            ? (int) now()->startOfDay()->diffInDays($this->bitis_tarihi, false)
            : null;
    }

    public function scopeAktif(Builder $q): Builder
    {
        return $q->where('aktif_mi', true);
    }

    /** @param  array{0: ?string, 1: ?string}  $aralik */
    public function scopeBitisAraliginda(Builder $q, array $aralik): Builder
    {
        [$baslangic, $bitis] = $aralik;

        return $q->when($baslangic, fn (Builder $x) => $x->where('bitis_tarihi', '>=', $baslangic))
            ->when($bitis, fn (Builder $x) => $x->where('bitis_tarihi', '<=', $bitis));
    }
}
