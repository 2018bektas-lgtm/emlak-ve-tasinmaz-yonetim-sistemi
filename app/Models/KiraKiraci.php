<?php

namespace App\Models;

use App\Enums\KiraciTipi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir kiracılık döneminin ana ya da alt kiracısı.
 *
 * Kiracı sözleşme dönemine bağlıdır: aynı dosya numarasında yıllar içinde
 * birbiri ardına farklı kiracılar olur. kira_sozlesme_id boşsa satır dosyanın
 * genel kiracısı sayılır.
 */
class KiraKiraci extends Model
{
    protected $table = 'kira_kiracilari';

    protected $fillable = ['kira_dosya_id', 'kira_sozlesme_id', 'tip', 'ad_unvan', 'tc_vergi_no', 'telefon', 'eposta', 'adres', 'iban', 'avukat'];

    protected function casts(): array
    {
        return ['tip' => KiraciTipi::class];
    }

    public function dosya(): BelongsTo
    {
        return $this->belongsTo(KiraDosyasi::class, 'kira_dosya_id');
    }

    public function sozlesme(): BelongsTo
    {
        return $this->belongsTo(KiraSozlesme::class, 'kira_sozlesme_id');
    }
}
