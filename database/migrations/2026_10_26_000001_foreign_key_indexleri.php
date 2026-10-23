<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Foreign key kolonlarına eksik indexler.
 *
 * MySQL her foreign key için kendiliğinden bir index açar; PostgreSQL AÇMAZ —
 * yalnızca kısıtı kurar. PostgreSQL'e geçişte MySQL'de bedava gelen 39 index
 * bu yüzden kayboldu ve her join, her alt sorgu, her silme kontrolü sıralı
 * tarama yapmaya başladı.
 *
 * En görünür etkisi kira haritasıydı: kümeleme sorgusundaki
 * `id in (select poligon_id from kira_alanlari ...)` alt sorgusu 101 bin
 * satırı tarıyordu (~82 ms) ve harita her hareketinde iki kez koşuyordu.
 *
 * Tek kolonlu ve indexsiz kalan tüm foreign key'ler listelendi; PostgreSQL
 * belgeleri de referans eylemleri (ON DELETE/UPDATE) yüzünden bunların
 * indexlenmesini önerir.
 */
return new class extends Migration
{
    /**
     * Tablo => indexlenecek foreign key kolonları.
     *
     * @var array<string, list<string>>
     */
    private const INDEXLER = [
        'ecrimisil_resimleri' => ['tutanak_id'],
        'emsal_bagimsiz_bolumler' => ['emsal_id'],
        'emsal_parselleri' => ['ilce_id'],
        'emsaller' => ['olusturan_id'],
        'hisse_goruslar' => ['tasinmaz_id'],
        'hisse_imar_evraklar' => ['tasinmaz_id'],
        'hisse_islem_gecmisi' => ['kullanici_id'],
        'hisse_tebligatlari' => ['tasinmaz_id'],
        'kira_alanlari' => ['hukuki_durum_id', 'is_kolu_id', 'kiracilik_durum_id', 'poligon_id', 'tasinmaz_id'],
        'kira_belgeleri' => ['yukleyen_id'],
        'kira_dosyalari' => ['ana_dosya_id', 'hukuki_durum_id', 'is_kolu_id', 'kiracilik_durum_id', 'muhasebe_niteligi_id', 'olusturan_id', 'sorumlu_personel_id'],
        'kira_poligonlari' => ['olusturan_id'],
        'kira_surec_olaylari' => ['olusturan_id'],
        'kira_tasinmazlari' => ['il_id', 'imar_durumu_id', 'tasinmaz_id'],
        'kullanicilar' => ['rol_id'],
        'lojmanlar' => ['il_id', 'ilce_id'],
        'rol_izin' => ['izin_id'],
        'tasinmaz_degerlemeleri' => ['hazirlayan_id', 'olusturan_id'],
        'tasinmaz_encumenleri' => ['tasinmaz_id'],
        'tasinmaz_imarlar' => ['imar_durumu_id'],
        'tasinmaz_kategori' => ['kayit_turu_id', 'muhasebe_kayit_id'],
        'tasinmaz_yapi' => ['kayit_turu_id', 'muhasebe_kayit_id'],
        'tasinmazlar' => ['ilce_id'],
    ];

    public function up(): void
    {
        foreach (self::INDEXLER as $tablo => $kolonlar) {
            foreach ($kolonlar as $kolon) {
                $ad = $this->indexAdi($tablo, $kolon);
                DB::statement("CREATE INDEX IF NOT EXISTS {$ad} ON {$tablo} ({$kolon})");
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXLER as $tablo => $kolonlar) {
            foreach ($kolonlar as $kolon) {
                DB::statement('DROP INDEX IF EXISTS '.$this->indexAdi($tablo, $kolon));
            }
        }
    }

    /** PostgreSQL'de tanımlayıcı sınırı 63 bayt; uzun adlar kısaltılır. */
    private function indexAdi(string $tablo, string $kolon): string
    {
        $ad = "{$tablo}_{$kolon}_index";

        return strlen($ad) <= 63 ? $ad : substr($ad, 0, 53).'_'.substr(md5($ad), 0, 8);
    }
};
