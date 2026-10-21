<?php

namespace App\Models;

use App\Support\Concerns\SinirKutusuTutar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Yeniden kullanılabilir kira geometrisi.
 *
 * Aynı poligon birden çok kira alanına bağlanabilir: bir parkta yıllar içinde
 * değişen kiracılar aynı sınırı kullanır, kayıt açarken poligon yeniden
 * çizilmez, haritadan seçilir.
 */
class KiraPoligon extends Model
{
    use SinirKutusuTutar;

    protected $table = 'kira_poligonlari';

    protected $fillable = [
        'ad', 'kaynak', 'mahalle_id', 'ada', 'parsel',
        'koordinat', 'lat', 'lng', 'alan', 'olusturan_id',
    ];

    protected function casts(): array
    {
        return [
            'koordinat' => 'array',
            'alan' => 'decimal:2',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'merkez_lat' => 'decimal:7',
            'merkez_lng' => 'decimal:7',
        ];
    }

    public function alanlar(): HasMany
    {
        return $this->hasMany(KiraAlan::class, 'poligon_id');
    }

    public function mahalle(): BelongsTo
    {
        return $this->belongsTo(Mahalle::class, 'mahalle_id');
    }

    public function olusturan(): BelongsTo
    {
        return $this->belongsTo(Kullanici::class, 'olusturan_id');
    }

    public function adaParsel(): ?string
    {
        return $this->ada && $this->parsel ? $this->ada.'/'.$this->parsel : null;
    }

    /** Haritada "bu poligonda kaç kayıt var" rozetinde kullanılır. */
    public function kayitSayisi(): int
    {
        return $this->alanlar()->count();
    }

    /**
     * Aynı parselin daha önce çizilmiş poligonu — kayıt açarken yeniden
     * çizmemek için önerilir.
     */
    public function scopeParselde(Builder $q, ?int $mahalleId, ?string $ada, ?string $parsel): Builder
    {
        return $q->where('mahalle_id', $mahalleId)->where('ada', $ada)->where('parsel', $parsel);
    }
}
