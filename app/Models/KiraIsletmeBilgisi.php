<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Abonelikler, teslim edilen demirbaşlar ve denetim bilgisi. */
class KiraIsletmeBilgisi extends Model
{
    protected $table = 'kira_isletme_bilgileri';

    protected $fillable = [
        'kira_dosya_id', 'su_abone_no', 'elektrik_abone_no', 'dogalgaz_abone_no',
        'abone_aciklama', 'demirbaslar', 'isyeri_teslim_tarihi', 'son_denetim_tarihi', 'denetim_notu',
    ];

    protected function casts(): array
    {
        return ['isyeri_teslim_tarihi' => 'date', 'son_denetim_tarihi' => 'date'];
    }

    public function dosya(): BelongsTo
    {
        return $this->belongsTo(KiraDosyasi::class, 'kira_dosya_id');
    }
}
