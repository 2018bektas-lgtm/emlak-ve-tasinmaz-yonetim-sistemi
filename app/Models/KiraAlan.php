<?php

namespace App\Models;

use App\Enums\KiraciTipi;
use App\Enums\KiraDoluBos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kira taşınmazı: kiraya verilen (ya da verilecek) tek bir yer.
 *
 * Kayıt akışının merkezi budur. Önce alan eklenir; kiraya verildiğinde dosya
 * numarası bağlanır. Boş bir alanın henüz dosyası olmayabilir. Geometri
 * alanın kendisinde değil, paylaşılabilir `kira_poligonlari`nda; poligonun
 * içine düşen envanter taşınmazı `tasinmaz_id` ile bağlanır.
 *
 * Kiracı, bedel ve tarih sütunları dosyanın yürürlükteki döneminden
 * doldurulur (dosyadanEsitle); formda ayrıca girilmez.
 */
class KiraAlan extends Model
{
    protected $table = 'kira_alanlari';

    protected $fillable = [
        'kira_dosya_id', 'poligon_id', 'tasinmaz_id', 'ad', 'adres', 'alan',
        'faaliyet_konusu', 'kiralama_amaci', 'is_kolu_id',
        'yillik_bedel', 'taksit_sayisi', 'tahliye_ihbarnamesi_mi',
        'baslangic_tarihi', 'bitis_tarihi', 'aktif_mi',
        'kiracilik_durum_id', 'hukuki_durum_id', 'dolu_bos',
        'kiraci_ad_unvan', 'alt_kiraci', 'aciklama',
    ];

    protected function casts(): array
    {
        return [
            'alan' => 'decimal:2',
            'yillik_bedel' => 'decimal:2',
            'baslangic_tarihi' => 'date',
            'bitis_tarihi' => 'date',
            'aktif_mi' => 'boolean',
            'tahliye_ihbarnamesi_mi' => 'boolean',
            'dolu_bos' => KiraDoluBos::class,
        ];
    }

    public function dosya(): BelongsTo
    {
        return $this->belongsTo(KiraDosyasi::class, 'kira_dosya_id');
    }

    public function poligon(): BelongsTo
    {
        return $this->belongsTo(KiraPoligon::class, 'poligon_id');
    }

    /** Poligonun içine düşen envanter taşınmazı. */
    public function tasinmaz(): BelongsTo
    {
        return $this->belongsTo(Tasinmaz::class, 'tasinmaz_id');
    }

    /**
     * Kiracı, bedel, süre ve hukuki durum dosyanın yürürlükteki döneminden
     * alınır. Dosya, dönem ya da alan kaydedildiğinde çağrılır; böylece
     * listede, haritada ve raporda alan ile dosya aynı şeyi söyler.
     */
    public function dosyadanEsitle(): void
    {
        $dosya = $this->kira_dosya_id
            ? KiraDosyasi::with(['kiracilar', 'aktifSozlesme.bedeller'])->find($this->kira_dosya_id)
            : null;

        if (! $dosya) {
            $this->update([
                'kiraci_ad_unvan' => null, 'yillik_bedel' => null, 'taksit_sayisi' => null,
                'baslangic_tarihi' => null, 'bitis_tarihi' => null,
            ]);

            return;
        }

        $donem = $dosya->aktifSozlesme;
        $this->update([
            'kiraci_ad_unvan' => $dosya->guncelKiraci()?->ad_unvan,
            'yillik_bedel' => $donem?->guncelBedel()?->yillik_bedel,
            'taksit_sayisi' => $donem?->taksit_sayisi,
            'baslangic_tarihi' => $donem?->baslangic_tarihi,
            'bitis_tarihi' => $donem?->bitis_tarihi,
            'hukuki_durum_id' => $dosya->hukuki_durum_id,
            'kiracilik_durum_id' => $dosya->kiracilik_durum_id,
            'tahliye_ihbarnamesi_mi' => $dosya->tahliye_ihbarnamesi_mi,
        ]);
    }

    public function isKolu(): BelongsTo
    {
        return $this->belongsTo(KiraIsKolu::class, 'is_kolu_id');
    }

    public function kiracilikDurum(): BelongsTo
    {
        return $this->belongsTo(KiraKiracilikDurum::class, 'kiracilik_durum_id');
    }

    public function hukukiDurum(): BelongsTo
    {
        return $this->belongsTo(KiraHukukiDurum::class, 'hukuki_durum_id');
    }

    public function belgeler(): HasMany
    {
        return $this->hasMany(KiraBelge::class, 'kira_alan_id');
    }

    /** Bitişe kalan gün; geçmişse negatif. */
    public function kalanGun(): ?int
    {
        // Sorgu `kalan_gun`'u seçtiyse onu kullan: Carbon'un diffInDays'i
        // pahalıdır ve harita binlerce kayıt için bunu çağırıyor. SQL'deki
        // `bitis_tarihi - CURRENT_DATE` bu hesabın aynısını verir.
        if (array_key_exists('kalan_gun', $this->attributes)) {
            return $this->attributes['kalan_gun'] === null
                ? null
                : (int) $this->attributes['kalan_gun'];
        }

        return $this->bitis_tarihi
            ? (int) now()->startOfDay()->diffInDays($this->bitis_tarihi, false)
            : null;
    }

    /**
     * Kiracı alan düzeyinde girilmemişse dosyanın güncel kiracısı geçerlidir.
     *
     * Dosyada birden çok kiracılık dönemi olabilir; yürürlükteki dönemin
     * kiracısı aranır, yoksa en son eklenen satır.
     */
    public function kiraci(): ?string
    {
        if ($this->kiraci_ad_unvan) {
            return $this->kiraci_ad_unvan;
        }

        $kiracilar = $this->dosya?->kiracilar;
        if (! $kiracilar) {
            return null;
        }

        $anaKiracilar = $kiracilar->where('tip', KiraciTipi::Ana);
        $aktifDonemId = $this->dosya?->aktifSozlesme?->id;

        return ($aktifDonemId ? $anaKiracilar->firstWhere('kira_sozlesme_id', $aktifDonemId) : null)?->ad_unvan
            ?? $anaKiracilar->sortByDesc('id')->first()?->ad_unvan;
    }

    public function scopeAktif(Builder $q): Builder
    {
        return $q->where('aktif_mi', true);
    }

    /** Sınır kutusu verilen alanla kesişen alanlar — poligon üzerinden. */
    public function scopeAlanda(Builder $q, array $bbox): Builder
    {
        return $q->whereIn('poligon_id', KiraPoligon::query()->select('id')->alanda($bbox));
    }
}
