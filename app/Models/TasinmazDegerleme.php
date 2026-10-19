<?php

namespace App\Models;

use App\Enums\KayitTipi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Taşınmaz değerleme raporu. tip = arsa (parselin / kurum hissesinin değeri)
 * ya da bb (tek bir bağımsız bölümün değeri). Hangi tipin açılabileceği
 * taşınmazın kayıt tipinden gelir — bkz. tipler().
 */
class TasinmazDegerleme extends Model
{
    protected $table = 'tasinmaz_degerlemeleri';

    public const TIPLER = [
        'arsa' => 'Arsa Değerlemesi',
        'bb' => 'Bağımsız Bölüm Değerlemesi',
    ];

    protected $fillable = [
        'tasinmaz_id', 'tip', 'yapi_id',
        'bb_no', 'kat', 'bb_nitelik', 'oda_sayisi', 'brut_alan',
        'hisse_alan', 'birim_fiyat', 'toplam_bedel',
        'rapor_no', 'rapor_tarihi', 'hazirlayan_id', 'olusturan_id', 'evrak', 'aciklama',
    ];

    protected function casts(): array
    {
        return [
            'rapor_tarihi' => 'date',
            'brut_alan' => 'decimal:2',
            'hisse_alan' => 'decimal:2',
            'birim_fiyat' => 'decimal:2',
            'toplam_bedel' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // Aynı rapor PDF'i birden çok kayıtta (bir binanın BB'leri) paylaşılabilir;
        // dosya ancak son kullanan kayıt silinince kaldırılır.
        static::deleted(function (self $d): void {
            if ($d->evrak && ! static::where('evrak', $d->evrak)->exists()) {
                Storage::disk('public')->delete($d->evrak);
            }
        });
    }

    public function tasinmaz(): BelongsTo
    {
        return $this->belongsTo(Tasinmaz::class, 'tasinmaz_id');
    }

    public function yapi(): BelongsTo
    {
        return $this->belongsTo(TasinmazYapi::class, 'yapi_id');
    }

    public function hazirlayan(): BelongsTo
    {
        return $this->belongsTo(Kullanici::class, 'hazirlayan_id');
    }

    public function olusturan(): BelongsTo
    {
        return $this->belongsTo(Kullanici::class, 'olusturan_id');
    }

    public function tipEtiketi(): string
    {
        return self::TIPLER[$this->tip] ?? $this->tip;
    }

    /** Değerlenen alan: BB'de brüt alan, arsada hisse alanı (yoksa parsel alanı). */
    public function degerlenenAlan(): ?float
    {
        $alan = $this->tip === 'bb' ? $this->brut_alan : ($this->hisse_alan ?? $this->tasinmaz?->alan);

        return $alan !== null ? (float) $alan : null;
    }

    /** Bağımsız bölümün kısa adı: "BB 3 · 2. kat · Konut". */
    public function bolumEtiketi(): string
    {
        return collect([
            $this->bb_no ? 'BB '.$this->bb_no : null,
            $this->kat !== null && $this->kat !== '' ? $this->kat.(is_numeric($this->kat) ? '. kat' : '') : null,
            $this->bb_nitelik,
        ])->filter()->implode(' · ');
    }

    /**
     * Taşınmazın kayıt tipine göre açılabilecek değerleme tipleri.
     * Boş parsel → yalnız arsa; kat mülkiyetli BBN → yalnız bağımsız bölüm;
     * kat mülkiyetsiz bina → ikisi de (arsanın tamamı ya da tek bir bölüm).
     *
     * @return array<int, string>
     */
    public static function tipler(Tasinmaz $t): array
    {
        return match ($t->kayit_tipi) {
            KayitTipi::KatMulkiyetli => ['bb'],
            KayitTipi::KatMulkiyetsizBina => ['arsa', 'bb'],
            default => ['arsa'],
        };
    }

    /** Kurumun aktif hisse toplamı (m²) — arsa değerlemesinde varsayılan alan. */
    public static function kurumHisseAlani(Tasinmaz $t): ?float
    {
        $hisseler = $t->relationLoaded('hisseler') ? $t->hisseler : $t->hisseler()->get();
        $toplam = $hisseler->where('hisse_durum', 'aktif')->sum(fn ($h) => (float) $h->hisse_yuzolcum);

        return $toplam > 0 ? round($toplam, 2) : ($t->alan !== null ? (float) $t->alan : null);
    }

    /** Liste/arama: konum, rapor no, BB no. */
    public function scopeAra($query, ?string $q)
    {
        $q = trim((string) $q);
        if ($q === '') {
            return $query;
        }

        // "1234/5" → ada 1234 + parsel 5
        $adaParsel = preg_match('~^\s*([\w-]+)\s*/\s*([\w-]+)\s*$~u', $q, $m) ? [$m[1], $m[2]] : null;

        return $query->where(function ($w) use ($q, $adaParsel) {
            $w->where('rapor_no', 'like', "%{$q}%")
                ->orWhere('bb_no', $q)
                ->orWhere('bb_nitelik', 'like', "%{$q}%")
                // Değerleme başına EXISTS yerine taşınmazlar bir kez süzülür
                ->orWhereIn('tasinmaz_id', Tasinmaz::query()->select('id')->where(function ($t) use ($q, $adaParsel) {
                    $t->where('ada', $q)
                        ->orWhere('parsel', $q)
                        ->when($adaParsel, fn ($x) => $x->orWhere(fn ($y) => $y->where('ada', $adaParsel[0])->where('parsel', $adaParsel[1])))
                        ->orWhereIn('ilce_id', Ilce::query()->select('id')->where('ad', 'like', "%{$q}%"))
                        ->orWhereIn('mahalle_id', Mahalle::query()->select('id')->where('ad', 'like', "%{$q}%"));
                }));
        });
    }
}
