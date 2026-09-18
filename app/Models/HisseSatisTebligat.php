<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HisseSatisTebligat extends Model
{
    protected $table = 'hisse_satis_tebligatlari';

    protected $fillable = [
        'basvuru_id',
        'grup_no',
        'tebligat_tarihi',
        'ulastigi_tarihi',
        'hisseye_dusen_yuzolcum',
        'birim_fiyat',
        'toplam_bedel',
        'odedi',
        'tebligat_ulasmadi',
        'aciklama',
    ];

    protected function casts(): array
    {
        return [
            'tebligat_tarihi' => 'date',
            'ulastigi_tarihi' => 'date',
            'hisseye_dusen_yuzolcum' => 'decimal:2',
            'birim_fiyat' => 'decimal:2',
            'toplam_bedel' => 'decimal:2',
            'odedi' => 'boolean',
            'tebligat_ulasmadi' => 'boolean',
        ];
    }

    public function basvuru(): BelongsTo
    {
        return $this->belongsTo(HisseBasvuru::class, 'basvuru_id');
    }

    /** 15 günlük ödeme süresi. */
    public function kalanGun(): ?int
    {
        if (! $this->ulastigi_tarihi) {
            return null;
        }
        $son = $this->ulastigi_tarihi->copy()->startOfDay()->addDays(15);

        return (int) now()->startOfDay()->diffInDays($son, false);
    }
}
