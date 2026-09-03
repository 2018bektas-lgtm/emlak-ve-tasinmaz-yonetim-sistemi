<?php

namespace App\Models;

use App\Enums\SatisDurumu;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir taşınmazın durum/ek bilgisi (arsa seviyesi):
 *   işgal, satış, meclis kararı, tahsis, üst hakkı, kira ve açıklama.
 * hasOne — her taşınmazın tek bir ek bilgi satırı vardır.
 */
class TasinmazEkbilgi extends Model
{
    protected $table = 'tasinmaz_ekbilgi';

    protected $fillable = [
        'tasinmaz_id',
        'isgal_durumu',
        'satis_durumu',
        'meclis_satis_karari_var',
        'tahsis_var',
        'ust_hakki_var',
        'kira_var',
        'aciklama',
    ];

    protected function casts(): array
    {
        return [
            'satis_durumu' => SatisDurumu::class,
            'meclis_satis_karari_var' => 'boolean',
            'tahsis_var' => 'boolean',
            'ust_hakki_var' => 'boolean',
            'kira_var' => 'boolean',
        ];
    }

    public function tasinmaz(): BelongsTo
    {
        return $this->belongsTo(Tasinmaz::class, 'tasinmaz_id');
    }
}
