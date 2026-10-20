<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * TÜFE oranları — kira artışının dayanağı. Oran ay bazında yayımlandığı için
 * (yıl, ay) benzersiz; hesaplama anında bedel satırına kopyalanır.
 */
class KiraTufeOrani extends Model
{
    protected $table = 'kira_tufe_oranlari';

    protected $fillable = ['yil', 'ay', 'oran', 'aciklama'];

    protected function casts(): array
    {
        return ['oran' => 'decimal:3'];
    }

    /** Verilen tarihin ayına ait oran; yoksa null. */
    public static function tariheGore(\DateTimeInterface $tarih): ?self
    {
        return static::where('yil', (int) $tarih->format('Y'))
            ->where('ay', (int) $tarih->format('n'))
            ->first();
    }
}
