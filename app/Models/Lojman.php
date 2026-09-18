<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lojman extends Model
{
    protected $table = 'lojmanlar';

    protected $fillable = [
        'tasinmaz_id', 'mudurluk_id',
        'il_id', 'ilce_id', 'mahalle_id', 'ada', 'parsel',
        'ad', 'blok', 'daire_no', 'kat', 'oda_sayisi',
        'alan_m2', 'tip', 'durum', 'aciklama',
    ];

    protected function casts(): array
    {
        return [
            'alan_m2' => 'decimal:2',
        ];
    }

    public function tasinmaz(): BelongsTo
    {
        return $this->belongsTo(Tasinmaz::class, 'tasinmaz_id');
    }

    public function mudurluk(): BelongsTo
    {
        return $this->belongsTo(Mudurluk::class, 'mudurluk_id');
    }

    public function il(): BelongsTo
    {
        return $this->belongsTo(Il::class, 'il_id');
    }

    public function ilce(): BelongsTo
    {
        return $this->belongsTo(Ilce::class, 'ilce_id');
    }

    public function mahalle(): BelongsTo
    {
        return $this->belongsTo(Mahalle::class, 'mahalle_id');
    }

    public function basvurular(): HasMany
    {
        return $this->hasMany(LojmanBasvuru::class, 'lojman_id')->orderByDesc('basvuru_tarihi');
    }

    public function tahsisler(): HasMany
    {
        return $this->hasMany(LojmanTahsis::class, 'lojman_id')->orderByDesc('baslangic_tarihi');
    }

    public function aktifTahsis(): ?LojmanTahsis
    {
        return $this->tahsisler()->whereNull('bitis_tarihi')->orWhere('bitis_tarihi', '>=', now()->toDateString())->first();
    }

    public function evraklar(): HasMany
    {
        return $this->hasMany(LojmanEvrak::class, 'lojman_id')->orderByDesc('id');
    }

    /** Kullanıcı müdürlük kapsamı (hisse satışıyla aynı mantık). */
    public function scopeMudurlukKapsami($query, ?Kullanici $kullanici = null)
    {
        $kullanici ??= auth()->user();
        if (! $kullanici instanceof Kullanici || $kullanici->izinVarMi('tasinmaz.tumunu-gor')) {
            return $query;
        }
        if (! $kullanici->mudurluk_id) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where('mudurluk_id', $kullanici->mudurluk_id);
    }
}
