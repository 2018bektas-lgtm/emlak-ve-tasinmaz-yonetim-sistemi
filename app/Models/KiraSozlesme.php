<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
