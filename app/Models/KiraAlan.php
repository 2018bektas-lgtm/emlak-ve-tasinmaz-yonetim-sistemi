<?php

namespace App\Models;

use App\Enums\KiraDoluBos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dosyada kiraya verilen tek bir alan.
 *
 * Referans sistemde alan, dosyanın içinde kendi kiracısı, tarihi, bedeli ve
 * doluluk durumu olan bağımsız bir kiralama birimi (5.693 alan / 4.353 dosya).
 * Geometri alanın kendisinde değil, paylaşılabilir `kira_poligonlari`nda.
 */
class KiraAlan extends Model
{
    protected $table = 'kira_alanlari';

    protected $fillable = [
        'kira_dosya_id', 'poligon_id', 'ad', 'adres', 'alan',
        'faaliyet_konusu', 'kiralama_amaci', 'is_kolu_id',
        'yillik_bedel', 'taksit_sayisi', 'tahliye_ihbarnamesi_mi',
        'baslangic_tarihi', 'bitis_tarihi', 'aktif_mi',
        'kiracilik_durum_id', 'hukuki_durum_id', 'dolu_bos',
        'kiraci_ad_unvan', 'alt_kiraci', 'aciklama',
    ];

    protected function casts(): array
    {
        return [
            'alan' => 'decimal:2',
            'yillik_bedel' => 'decimal:2',
            'baslangic_tarihi' => 'date',
            'bitis_tarihi' => 'date',
            'aktif_mi' => 'boolean',
            'tahliye_ihbarnamesi_mi' => 'boolean',
            'dolu_bos' => KiraDoluBos::class,
        ];
    }

    public function dosya(): BelongsTo
    {
        return $this->belongsTo(KiraDosyasi::class, 'kira_dosya_id');
    }

    public function poligon(): BelongsTo
    {
        return $this->belongsTo(KiraPoligon::class, 'poligon_id');
    }

    public function isKolu(): BelongsTo
    {
        return $this->belongsTo(KiraIsKolu::class, 'is_kolu_id');
    }

    public function kiracilikDurum(): BelongsTo
    {
        return $this->belongsTo(KiraKiracilikDurum::class, 'kiracilik_durum_id');
    }

    public function hukukiDurum(): BelongsTo
    {
        return $this->belongsTo(KiraHukukiDurum::class, 'hukuki_durum_id');
    }

    public function belgeler(): HasMany
    {
        return $this->hasMany(KiraBelge::class, 'kira_alan_id');
    }

    /** Bitişe kalan gün; geçmişse negatif. */
    public function kalanGun(): ?int
    {
        return $this->bitis_tarihi
            ? (int) now()->startOfDay()->diffInDays($this->bitis_tarihi, false)
            : null;
    }

    /** Kiracı alan düzeyinde girilmemişse dosyanın ana kiracısı geçerlidir. */
    public function kiraci(): ?string
    {
        return $this->kiraci_ad_unvan
            ?: $this->dosya?->kiracilar->firstWhere('tip', \App\Enums\KiraciTipi::Ana)?->ad_unvan;
    }

    public function scopeAktif(Builder $q): Builder
    {
        return $q->where('aktif_mi', true);
    }

    /** Sınır kutusu verilen alanla kesişen alanlar — poligon üzerinden. */
    public function scopeAlanda(Builder $q, array $bbox): Builder
    {
        return $q->whereIn('poligon_id', KiraPoligon::query()->select('id')->alanda($bbox));
    }
}
