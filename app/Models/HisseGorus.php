<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HisseGorus extends Model
{
    protected $table = 'hisse_goruslar';

    protected $fillable = [
        'grup_no',
        'tasinmaz_id',
        'gorus_sube',
        'giden_tarih',
        'giden_yazi',
        'giden_evrak',
        'gelen_tarih',
        'gelen_yazi',
        'gelen_evrak',
        'engel_var_yok',
    ];

    protected function casts(): array
    {
        return [
            'giden_tarih' => 'date',
            'gelen_tarih' => 'date',
        ];
    }

    public function tasinmaz(): BelongsTo
    {
        return $this->belongsTo(Tasinmaz::class, 'tasinmaz_id');
    }
}
