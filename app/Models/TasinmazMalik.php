<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TasinmazMalik extends Model
{
    protected $table = 'tasinmaz_malikleri';

    protected $fillable = [
        'tasinmaz_id',
        'ad_soyad',
        'tc_kimlik',
        'tapu_hisse',
        'adres',
        'gsm_no',
    ];

    protected function casts(): array
    {
        return ['tapu_hisse' => 'decimal:2'];
    }

    public function tasinmaz(): BelongsTo
    {
        return $this->belongsTo(Tasinmaz::class, 'tasinmaz_id');
    }
}
