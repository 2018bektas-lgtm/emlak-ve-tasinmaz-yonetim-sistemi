<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** İhale komisyonu üyesi. Referansta Üye-1..4 diye sabit sütunlardı. */
class KiraIhaleKomisyonUyesi extends Model
{
    protected $table = 'kira_ihale_komisyonu';

    protected $fillable = ['kira_ihale_id', 'gorev', 'ad_soyad', 'unvan', 'sira'];

    public function ihale(): BelongsTo
    {
        return $this->belongsTo(KiraIhale::class, 'kira_ihale_id');
    }

    public function baskanMi(): bool
    {
        return $this->gorev === 'baskan';
    }
}
