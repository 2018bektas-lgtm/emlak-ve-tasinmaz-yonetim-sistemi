<?php

namespace App\Models;

use App\Enums\KiraciTipi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dosyanın ana ya da alt kiracısı. Bir dosyada birden çok alt kiracı olabilir. */
class KiraKiraci extends Model
{
    protected $table = 'kira_kiracilari';

    protected $fillable = ['kira_dosya_id', 'tip', 'ad_unvan', 'tc_vergi_no', 'telefon', 'eposta', 'adres', 'iban', 'avukat'];

    protected function casts(): array
    {
        return ['tip' => KiraciTipi::class];
    }

    public function dosya(): BelongsTo
    {
        return $this->belongsTo(KiraDosyasi::class, 'kira_dosya_id');
    }
}
