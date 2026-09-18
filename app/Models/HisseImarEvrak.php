<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HisseImarEvrak extends Model
{
    protected $table = 'hisse_imar_evraklar';

    protected $fillable = [
        'grup_no',
        'tasinmaz_id',
        'imar_giden_yazi',
        'imar_giden_tarih',
        'imar_giden_evrak',
        'imar_gelen_yazi',
        'imar_gelen_tarih',
        'imar_gelen_evrak',
        'imar_durum',
        'emsal',
        'yencok',
        'plan_notlari',
    ];

    protected function casts(): array
    {
        return [
            'imar_giden_tarih' => 'date',
            'imar_gelen_tarih' => 'date',
        ];
    }

    public function tasinmaz(): BelongsTo
    {
        return $this->belongsTo(Tasinmaz::class, 'tasinmaz_id');
    }
}
