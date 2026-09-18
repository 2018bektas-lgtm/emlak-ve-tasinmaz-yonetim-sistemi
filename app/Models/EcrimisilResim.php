<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcrimisilResim extends Model
{
    protected $table = 'ecrimisil_resimleri';

    protected $fillable = [
        'isgalci_id',
        'tutanak_id',
        'dosya_yolu',
        'aciklama',
    ];

    public function isgalci(): BelongsTo
    {
        return $this->belongsTo(EcrimisilIsgalci::class, 'isgalci_id');
    }

    public function tutanak(): BelongsTo
    {
        return $this->belongsTo(EcrimisilTutanak::class, 'tutanak_id');
    }
}
