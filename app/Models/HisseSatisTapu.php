<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HisseSatisTapu extends Model
{
    protected $table = 'hisse_satis_tapular';

    protected $fillable = [
        'basvuru_id',
        'grup_no',
        'tescil_tarihi',
        'yevmiye_no',
        'tescil_edilen_yuzolcum',
        'tescil_evrak',
        'aciklama',
        'envanter_islem', // tescilin envantere yaptığı değişiklikler — geri alma için (HisseSureci)
    ];

    protected function casts(): array
    {
        return [
            'tescil_tarihi' => 'date',
            'tescil_edilen_yuzolcum' => 'decimal:2',
            'envanter_islem' => 'array',
        ];
    }

    public function basvuru(): BelongsTo
    {
        return $this->belongsTo(HisseBasvuru::class, 'basvuru_id');
    }
}
