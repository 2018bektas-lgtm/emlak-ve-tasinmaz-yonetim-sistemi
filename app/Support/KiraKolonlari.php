<?php

namespace App\Support;

use App\Models\Kullanici;
use App\Models\KullaniciKolonTercihi;

/**
 * Kira listesinin kolon kataloğu ve kullanıcı tercihi.
 *
 * Referans sistemde sorgu sonucu 252 kolonluk bir havuzdan seçiliyordu;
 * burada katalog işe yarayan alanlarla sınırlı ve gruplu. Varsayılan sette
 * yalnızca ekranda okunabilir kadarı açık — kullanıcı gerisini açabilir.
 */
class KiraKolonlari
{
    public const TABLO = 'kira';

    /** @return array<string, string> */
    public static function katalog(): array
    {
        return [
            'dosya_no' => 'Dosya No',
            'eski_dosya_no' => 'Eski Dosya No',
            'ana_dosya' => 'Ana Dosya',
            'kiraci' => 'Ana Kiracı',
            'alt_kiraci' => 'Alt Kiracı',
            'tc_vergi' => 'TC / Vergi No',
            'telefon' => 'Telefon',
            'isyeri' => 'İşyeri Adı',
            'is_kolu' => 'İş Kolu',
            'ilce' => 'İlçe',
            'mahalle' => 'Mahalle',
            'ada_parsel' => 'Ada / Parsel',
            'adres' => 'Kiralanan Yerin Adresi',
            'yuzolcum' => 'Yüzölçüm (m²)',
            'imar' => 'İmar Durumu',
            'arac_kapasitesi' => 'Araç Kapasitesi',
            'kira_donemi' => 'Kira Dönemi',
            'kalan_gun' => 'Kalan Gün',
            'sure' => 'Kira Süresi',
            'yillik_bedel' => 'Yıllık Bedel (₺)',
            'aylik_bedel' => 'Aylık Bedel (₺)',
            'taksit' => 'Taksit Sayısı',
            'odeme_sekli' => 'Ödeme Şekli',
            'sozlesme_no' => 'Sözleşme No',
            'sozlesme_tarihi' => 'Sözleşme Tarihi',
            'dolu_bos' => 'Dolu / Boş',
            'ihale' => 'İhale Durumu',
            'hukuki_durum' => 'Hukuki Durum',
            'kiracilik_durum' => 'Kiracılık Durumu',
            'tahliye' => 'Tahliye İhbarnamesi',
            'muhasebe' => 'Muhasebe Niteliği',
            'sorumlu' => 'Sorumlu Personel',
            'uyari' => 'Uyarı',
            'aciklama' => 'Açıklama',
        ];
    }

    /** @return list<string> */
    public static function anahtarlar(): array
    {
        return array_keys(self::katalog());
    }

    /**
     * Kolon seçim panelindeki gruplar.
     *
     * @return array<string, array{ikon:string, kolonlar:array<string,string>}>
     */
    public static function gruplar(): array
    {
        $k = self::katalog();
        $grup = fn (array $anahtarlar) => array_intersect_key($k, array_flip($anahtarlar));

        return [
            'Dosya' => [
                'ikon' => 'file',
                'kolonlar' => $grup(['dosya_no', 'eski_dosya_no', 'ana_dosya', 'isyeri', 'is_kolu']),
            ],
            'Kiracı' => [
                'ikon' => 'users',
                'kolonlar' => $grup(['kiraci', 'alt_kiraci', 'tc_vergi', 'telefon']),
            ],
            'Konum' => [
                'ikon' => 'pin',
                'kolonlar' => $grup(['ilce', 'mahalle', 'ada_parsel', 'adres', 'yuzolcum', 'imar', 'arac_kapasitesi']),
            ],
            'Sözleşme' => [
                'ikon' => 'calendar',
                'kolonlar' => $grup(['kira_donemi', 'kalan_gun', 'sure', 'sozlesme_no', 'sozlesme_tarihi', 'taksit', 'odeme_sekli']),
            ],
            'Bedel' => [
                'ikon' => 'coin',
                'kolonlar' => $grup(['yillik_bedel', 'aylik_bedel', 'muhasebe']),
            ],
            'Durum' => [
                'ikon' => 'flag',
                'kolonlar' => $grup(['dolu_bos', 'ihale', 'hukuki_durum', 'kiracilik_durum', 'tahliye']),
            ],
            'Diğer' => [
                'ikon' => 'note',
                'kolonlar' => $grup(['sorumlu', 'uyari', 'aciklama']),
            ],
        ];
    }

    /**
     * Varsayılan görünüm: ekranda rahat okunan sekiz kolon. Referansta da
     * sorgu açılışında 252 kolondan 23'ü seçili geliyordu.
     *
     * @return list<string>
     */
    public static function varsayilan(): array
    {
        return ['dosya_no', 'kiraci', 'isyeri', 'is_kolu', 'ilce', 'ada_parsel', 'kira_donemi', 'dolu_bos'];
    }

    /** @return list<string> */
    public static function kullaniciIcin(?Kullanici $kullanici): array
    {
        if (! $kullanici) {
            return self::varsayilan();
        }

        $tercih = KullaniciKolonTercihi::query()
            ->where('kullanici_id', $kullanici->id)
            ->where('tablo_anahtari', self::TABLO)
            ->first();

        if (! $tercih || ! is_array($tercih->kolonlar) || $tercih->kolonlar === []) {
            return self::varsayilan();
        }

        $secilen = array_values(array_intersect($tercih->kolonlar, self::anahtarlar()));

        return $secilen !== [] ? $secilen : self::varsayilan();
    }
}
