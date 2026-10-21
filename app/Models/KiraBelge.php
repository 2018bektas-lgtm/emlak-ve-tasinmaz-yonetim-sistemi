<?php

namespace App\Models;

use App\Enums\KiraBelgeTuru;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dosyaya eklenen türlenmiş belge (encümen kararı, sözleşme, yazışma...). */
class KiraBelge extends Model
{
    protected $table = 'kira_belgeleri';

    protected $fillable = ['kira_dosya_id', 'kira_alan_id', 'tur', 'ad', 'dosya_yolu', 'boyut', 'aciklama', 'yukleyen_id'];

    protected function casts(): array
    {
        return ['tur' => KiraBelgeTuru::class];
    }

    public function dosya(): BelongsTo
    {
        return $this->belongsTo(KiraDosyasi::class, 'kira_dosya_id');
    }

    /** Belge alana da bağlanabilir; referansta belgeler alan düzeyinde tutuluyor. */
    public function alan(): BelongsTo
    {
        return $this->belongsTo(KiraAlan::class, 'kira_alan_id');
    }

    public function yukleyen(): BelongsTo
    {
        return $this->belongsTo(Kullanici::class, 'yukleyen_id');
    }

    public function url(): string
    {
        return asset('storage/'.$this->dosya_yolu);
    }
}
