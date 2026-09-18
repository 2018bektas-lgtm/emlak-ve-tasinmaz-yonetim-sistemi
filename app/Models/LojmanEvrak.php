<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LojmanEvrak extends Model
{
    protected $table = 'lojman_evraklari';

    protected $fillable = [
        'basvuru_id', 'lojman_id', 'kategori', 'evrak_no',
        'evrak_tarihi', 'dosya_yolu', 'aciklama',
    ];

    protected function casts(): array
    {
        return [
            'evrak_tarihi' => 'date',
        ];
    }

    public function basvuru(): BelongsTo
    {
        return $this->belongsTo(LojmanBasvuru::class, 'basvuru_id');
    }

    public function lojman(): BelongsTo
    {
        return $this->belongsTo(Lojman::class, 'lojman_id');
    }
}
