<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lojman (konut birimi).
 *
 * Durum kuralı: "dolu" elle seçilmez, aktif tahsisten türetilir. Elle
 * seçilebilenler: boş / bakımda / kullanım dışı. Aktif tahsis bitince lojman
 * kendiliğinden boşa düşer (bkz. efektifDurum, scopeDurumu, durumYenile).
 */
class Lojman extends Model
{
    protected $table = 'lojmanlar';

    public const DURUMLAR = [
        'bos' => 'Boş',
        'dolu' => 'Dolu',
        'isgalde' => 'İşgalde',
        'bakimda' => 'Bakımda',
        'kullanim-disi' => 'Kullanım Dışı',
    ];

    /** Elle seçilebilen durumlar ("dolu" tahsisten türetilir). */
    public const ELLE_DURUMLAR = ['bos', 'isgalde', 'bakimda', 'kullanim-disi'];

    /** Tahsis yapılamayan durumlar. */
    public const TAHSIS_KAPALI = ['isgalde', 'bakimda', 'kullanim-disi'];

    /** Kamu konutları yönetmeliğindeki tahsis türleri (öneri listesi). */
    public const TIPLER = ['Özel Tahsisli', 'Görev Tahsisli', 'Sıra Tahsisli', 'Hizmet Tahsisli'];

    protected $fillable = [
        'tasinmaz_id',
        'il_id', 'ilce_id', 'mahalle_id', 'ada', 'parsel',
        'ad', 'blok', 'daire_no', 'kat', 'oda_sayisi',
        'alan_m2', 'tip', 'durum', 'aciklama',
    ];

    protected function casts(): array
    {
        return [
            'alan_m2' => 'decimal:2',
            'kat' => 'integer',
            'oda_sayisi' => 'integer',
        ];
    }

    public function tasinmaz(): BelongsTo
    {
        return $this->belongsTo(Tasinmaz::class, 'tasinmaz_id');
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
        return $this->hasMany(LojmanTahsis::class, 'lojman_id')->orderByDesc('baslangic_tarihi')->orderByDesc('id');
    }

    /** Bitişi olmayan ya da bugün/ileride biten tahsisler. */
    public function aktifTahsisler(): HasMany
    {
        return $this->hasMany(LojmanTahsis::class, 'lojman_id')->aktif();
    }

    /**
     * Şu anki (ya da başlayacak) tahsis. Önceki sürümde gruplanmamış orWhere
     * yüzünden başka lojmanın tahsisini döndürebiliyordu.
     */
    public function aktifTahsis(): ?LojmanTahsis
    {
        if ($this->relationLoaded('aktifTahsisler')) {
            return $this->aktifTahsisler->sortBy('baslangic_tarihi')->first();
        }
        if ($this->relationLoaded('tahsisler')) {
            return $this->tahsisler->sortBy('baslangic_tarihi')->first(fn (LojmanTahsis $t) => $t->aktifMi());
        }

        return $this->aktifTahsisler()->orderBy('baslangic_tarihi')->first();
    }

    public function evraklar(): HasMany
    {
        return $this->hasMany(LojmanEvrak::class, 'lojman_id')->orderByDesc('evrak_tarihi')->orderByDesc('id');
    }

    /** Ekranda gösterilecek gerçek durum (süresi dolmuş tahsis "dolu" göstermez). */
    public function efektifDurum(): string
    {
        if ($this->aktifTahsis()) {
            return 'dolu';
        }

        return $this->durum === 'dolu' ? 'bos' : ($this->durum ?: 'bos');
    }

    public function durumEtiketi(): string
    {
        return self::DURUMLAR[$this->efektifDurum()];
    }

    /** Kayıtlı durumu tahsislere göre eşitler (tahsis eklenince/bitince/silinince). */
    public function durumYenile(): void
    {
        $aktifVar = $this->aktifTahsisler()->exists();
        $yeni = match (true) {
            $aktifVar => 'dolu',
            $this->durum === 'dolu' => 'bos',
            default => $this->durum,
        };
        if ($yeni !== $this->durum) {
            $this->forceFill(['durum' => $yeni])->save();
        }
    }

    /** Sorgu düzeyinde efektif durum filtresi. */
    public function scopeDurumu($query, ?string $durum)
    {
        return match ($durum) {
            'dolu' => $query->whereHas('aktifTahsisler'),
            'bos' => $query->whereDoesntHave('aktifTahsisler')->whereIn('durum', ['bos', 'dolu']),
            'isgalde', 'bakimda', 'kullanim-disi' => $query->whereDoesntHave('aktifTahsisler')->where('durum', $durum),
            default => $query,
        };
    }

    /** Blok / daire / kat kısa gösterimi: "A Blok · Daire 3 · 2. kat" */
    public function konumKisa(): string
    {
        $p = [];
        if ($this->blok) {
            $p[] = $this->blok.' Blok';
        }
        if ($this->daire_no) {
            $p[] = 'Daire '.$this->daire_no;
        }
        if ($this->kat !== null) {
            $p[] = $this->kat < 0 ? 'Bodrum '.abs($this->kat) : ($this->kat === 0 ? 'Zemin kat' : $this->kat.'. kat');
        }

        return implode(' · ', $p);
    }
}
