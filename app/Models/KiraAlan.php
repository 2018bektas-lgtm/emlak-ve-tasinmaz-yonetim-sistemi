<?php

namespace App\Models;

use App\Support\Concerns\SinirKutusuTutar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dosyada kiraya verilen tek bir alan; kendi geometrisi vardır. */
class KiraAlan extends Model
{
    use SinirKutusuTutar;

    protected $table = 'kira_alanlari';

    protected $fillable = [
        'kira_dosya_id', 'ad', 'adres', 'alan', 'faaliyet_konusu', 'kiralama_amaci',
        'is_kolu_id', 'yillik_bedel', 'baslangic_tarihi', 'bitis_tarihi', 'aktif_mi',
        'aciklama', 'koordinat', 'lat', 'lng',
    ];

    protected function casts(): array
    {
        return [
            'koordinat' => 'array',
            'alan' => 'decimal:2', 'yillik_bedel' => 'decimal:2',
            'baslangic_tarihi' => 'date', 'bitis_tarihi' => 'date',
            'aktif_mi' => 'boolean',
            'lat' => 'decimal:7', 'lng' => 'decimal:7',
            'merkez_lat' => 'decimal:7', 'merkez_lng' => 'decimal:7',
        ];
    }

    public function dosya(): BelongsTo
    {
        return $this->belongsTo(KiraDosyasi::class, 'kira_dosya_id');
    }

    public function isKolu(): BelongsTo
    {
        return $this->belongsTo(KiraIsKolu::class, 'is_kolu_id');
    }
}
