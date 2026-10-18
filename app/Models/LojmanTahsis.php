<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LojmanTahsis extends Model
{
    protected $table = 'lojman_tahsisleri';

    protected $fillable = [
        'lojman_id', 'basvuru_id', 'ad_soyad', 'sicil_no',
        'baslangic_tarihi', 'bitis_tarihi', 'karar_no', 'karar_tarihi', 'aciklama',
    ];

    protected function casts(): array
    {
        return [
            'baslangic_tarihi' => 'date',
            'bitis_tarihi' => 'date',
            'karar_tarihi' => 'date',
        ];
    }

    public function lojman(): BelongsTo
    {
        return $this->belongsTo(Lojman::class, 'lojman_id');
    }

    public function basvuru(): BelongsTo
    {
        return $this->belongsTo(LojmanBasvuru::class, 'basvuru_id');
    }

    /** Bitişi yok ya da bugün/ileride. */
    public function scopeAktif($query)
    {
        return $query->where(fn ($w) => $w->whereNull('bitis_tarihi')->orWhereDate('bitis_tarihi', '>=', now()->toDateString()));
    }

    public function aktifMi(): bool
    {
        return ! $this->bitis_tarihi || $this->bitis_tarihi->gte(now()->startOfDay());
    }

    /** Başlangıcı ileri tarihte olan (henüz başlamamış) tahsis. */
    public function baslamadiMi(): bool
    {
        return $this->baslangic_tarihi && $this->baslangic_tarihi->gt(now()->startOfDay());
    }

    /** Oturulan gün sayısı (başlamamışsa null; sürüyorsa bugüne kadar). */
    public function gunSayisi(): ?int
    {
        if (! $this->baslangic_tarihi || $this->baslamadiMi()) {
            return null;
        }
        $son = $this->bitis_tarihi && $this->bitis_tarihi->lt(now()) ? $this->bitis_tarihi : now();

        return (int) $this->baslangic_tarihi->diffInDays($son) + 1;
    }
}
