<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mahalle extends Model
{
    protected $table = 'mahalleler';

    protected $fillable = [
        'tkgm_id',
        'ilce_id',
        'ad',
    ];

    protected function casts(): array
    {
        return [
            'tkgm_id' => 'integer',
            'ilce_id' => 'integer',
        ];
    }

    public function ilce(): BelongsTo
    {
        return $this->belongsTo(Ilce::class, 'ilce_id');
    }
}
