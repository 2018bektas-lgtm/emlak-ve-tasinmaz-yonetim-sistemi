<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcrimisilRapor extends Model
{
    protected $table = 'ecrimisil_raporlari';

    protected $fillable = [
        'isgalci_id',
        'rapor_no',
        'rapor_tarihi',
        'fiyat',
        'dosya_yolu',
        'aciklama',
    ];

    protected function casts(): array
    {
        return [
            'rapor_tarihi' => 'date',
            'fiyat' => 'decimal:2',
        ];
    }

    public function isgalci(): BelongsTo
    {
        return $this->belongsTo(EcrimisilIsgalci::class, 'isgalci_id');
    }
}
