<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TasinmazHisse extends Model
{
    protected $table = 'tasinmaz_hisseler';

    protected $fillable = [
        'tasinmaz_id',
        'hisse_no',
        'hisse_yuzolcum',
        'edinme_sekli',
        'yevmiye_no',
        'edinme_tarihi',
        'kayitlardan_cikis',
        'kayitlardan_cikistarihi',
        'hisse_durum',
        'islem_tipi',
        'maliyet_bedeli',
        'rayic_bedel',
        'emlak_vd',
        'iz_bedeli',
        'sira',
    ];

    protected function casts(): array
    {
        return [
            'hisse_yuzolcum' => 'decimal:2',
            'maliyet_bedeli' => 'decimal:2',
            'rayic_bedel' => 'decimal:2',
            'emlak_vd' => 'decimal:2',
            'iz_bedeli' => 'decimal:2',
            'edinme_tarihi' => 'date',
            'kayitlardan_cikistarihi' => 'date',
            'sira' => 'integer',
        ];
    }

    public function tasinmaz(): BelongsTo
    {
        return $this->belongsTo(Tasinmaz::class, 'tasinmaz_id');
    }
}
