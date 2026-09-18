<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HisseTebligat extends Model
{
    protected $table = 'hisse_tebligatlari';

    protected $fillable = [
        'grup_no',
        'tasinmaz_id',
        'ad_soyad',
        'tc_kimlik',
        'tapu_hisse',
        'tebligat_tarihi',
        'ulastigi_tarihi',
        'basvurdu',
        'tebligat_ulasmadi',
    ];

    protected function casts(): array
    {
        return [
            'tebligat_tarihi' => 'date',
            'ulastigi_tarihi' => 'date',
            'basvurdu' => 'boolean',
            'tebligat_ulasmadi' => 'boolean',
            'tapu_hisse' => 'decimal:2',
        ];
    }

    public function tasinmaz(): BelongsTo
    {
        return $this->belongsTo(Tasinmaz::class, 'tasinmaz_id');
    }

    /** 15 günlük itiraz süresi — negatif ise süre geçmiş. */
    public function kalanGun(): ?int
    {
        if (! $this->ulastigi_tarihi) {
            return null;
        }
        $son = $this->ulastigi_tarihi->copy()->startOfDay()->addDays(15);

        return (int) now()->startOfDay()->diffInDays($son, false);
    }
}
