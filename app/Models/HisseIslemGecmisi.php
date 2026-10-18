<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Hisse satışı dosyasında yapılan işlemlerin kaydı (kim, ne zaman, ne yaptı). */
class HisseIslemGecmisi extends Model
{
    protected $table = 'hisse_islem_gecmisi';

    protected $fillable = [
        'grup_no',
        'basvuru_id',
        'kullanici_id',
        'islem',
        'aciklama',
    ];

    public function kullanici(): BelongsTo
    {
        return $this->belongsTo(Kullanici::class, 'kullanici_id');
    }
}
