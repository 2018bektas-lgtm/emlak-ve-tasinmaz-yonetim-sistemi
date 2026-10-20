<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** İhaleye katılan kişi/firma; kazanan kazandi_mi ile işaretlenir. */
class KiraIhaleKatilimci extends Model
{
    protected $table = 'kira_ihale_katilimcilari';

    protected $fillable = ['kira_ihale_id', 'ad_unvan', 'tc_vergi_no', 'telefon', 'teklif_bedeli', 'kazandi_mi', 'aciklama'];

    protected function casts(): array
    {
        return ['teklif_bedeli' => 'decimal:2', 'kazandi_mi' => 'boolean'];
    }

    public function ihale(): BelongsTo
    {
        return $this->belongsTo(KiraIhale::class, 'kira_ihale_id');
    }
}
