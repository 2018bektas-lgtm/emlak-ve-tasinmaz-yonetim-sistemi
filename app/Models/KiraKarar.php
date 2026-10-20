<?php

namespace App\Models;

use App\Enums\KiraKararTuru;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Encümen / meclis / olur / başkanlık onayı — türü `tur` ile ayrılır. */
class KiraKarar extends Model
{
    protected $table = 'kira_kararlari';

    protected $fillable = ['kira_dosya_id', 'tur', 'karar_no', 'karar_tarihi', 'ilgili_kurum', 'aciklama', 'evrak'];

    protected function casts(): array
    {
        return ['tur' => KiraKararTuru::class, 'karar_tarihi' => 'date'];
    }

    public function dosya(): BelongsTo
    {
        return $this->belongsTo(KiraDosyasi::class, 'kira_dosya_id');
    }
}
