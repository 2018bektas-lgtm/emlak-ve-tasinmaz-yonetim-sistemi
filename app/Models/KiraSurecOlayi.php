<?php

namespace App\Models;

use App\Enums\KiraOlayTuru;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dosyanın zaman çizelgesi: devir, fesih, tebligat, tahliye ihbarnamesi... */
class KiraSurecOlayi extends Model
{
    protected $table = 'kira_surec_olaylari';

    protected $fillable = [
        'kira_dosya_id', 'tur', 'olay_tarihi', 'dilekce_tarihi', 'onay_no', 'onay_tarihi',
        'ulasma_tarihi', 'ilgili_kisi', 'aciklama', 'evrak', 'olusturan_id',
    ];

    protected function casts(): array
    {
        return [
            'tur' => KiraOlayTuru::class,
            'olay_tarihi' => 'date',
            'dilekce_tarihi' => 'date',
            'onay_tarihi' => 'date',
            'ulasma_tarihi' => 'date',
        ];
    }

    public function dosya(): BelongsTo
    {
        return $this->belongsTo(KiraDosyasi::class, 'kira_dosya_id');
    }

    public function olusturan(): BelongsTo
    {
        return $this->belongsTo(Kullanici::class, 'olusturan_id');
    }
}
