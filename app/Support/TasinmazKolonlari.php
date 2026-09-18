<?php

namespace App\Support;

use App\Models\Kullanici;
use App\Models\KullaniciKolonTercihi;

class TasinmazKolonlari
{
    public static function katalog(): array
    {
        return [
            'tkgm_parsel' => 'TKGM Parsel',
            'takbis' => 'TAKBİS Zemin No',
            'ilce' => 'İlçe',
            'mahalle' => 'Mahalle',
            'mudurluk' => 'Müdürlük',
            'ada' => 'Ada No',
            'parsel' => 'Parsel No',
            'alan' => 'Tapu Yüzölçüm (m²)',
            'nitelik' => 'Taşınmaz Niteliği',
            'hisse_durumu' => 'Hisse Durumu',
            'hisse_m2' => 'Hisseye Düşen (m²)',
            'bb_no' => 'B.Bölüm No',
            'blok_no' => 'Blok No',
            'kat_no' => 'Kat No',
            'imar' => 'İmar Durumu',
            'mulkiyet' => 'Mülkiyet Durumu',
            'muhasebe' => 'Muhasebe Niteliği',
            'dosya' => 'Dosya/Resim',
            'satis' => 'Satış Durumu',
            'bina' => 'Bina Durum',
            'aciklama' => 'Tüm Açıklamalar',
            'tahsis' => 'Tahsis',
            'ust_hakki' => 'Üst Hakkı',
            'kira' => 'Kira',
            'meclis' => 'Meclis Satış Kararı',
            'geometri' => 'Geometri',
            'ek_rapor' => 'Ek Rapor',
        ];
    }

    public static function anahtarlar(): array
    {
        return array_keys(self::katalog());
    }

    /**
     * Kolonları anlamlı gruplara ayır — kolon seçim panelinde kullanılır.
     *
     * @return array<string, array{ikon:string, kolonlar:array<string,string>}>
     */
    public static function gruplar(): array
    {
        $k = self::katalog();

        return [
            'Kimlik & Konum' => [
                'ikon' => 'pin',
                'kolonlar' => array_intersect_key($k, array_flip(['tkgm_parsel', 'takbis', 'mudurluk', 'ilce', 'mahalle', 'ada', 'parsel'])),
            ],
            'Alan & Nitelik' => [
                'ikon' => 'ruler',
                'kolonlar' => array_intersect_key($k, array_flip(['alan', 'nitelik', 'hisse_durumu', 'hisse_m2', 'mulkiyet'])),
            ],
            'Bağımsız Bölüm' => [
                'ikon' => 'building',
                'kolonlar' => array_intersect_key($k, array_flip(['bb_no', 'blok_no', 'kat_no', 'bina'])),
            ],
            'İmar & Muhasebe' => [
                'ikon' => 'layers',
                'kolonlar' => array_intersect_key($k, array_flip(['imar', 'muhasebe', 'ek_rapor'])),
            ],
            'Durum Bayrakları' => [
                'ikon' => 'flag',
                'kolonlar' => array_intersect_key($k, array_flip(['satis', 'tahsis', 'ust_hakki', 'kira', 'meclis'])),
            ],
            'Belge & Diğer' => [
                'ikon' => 'file',
                'kolonlar' => array_intersect_key($k, array_flip(['dosya', 'geometri', 'aciklama'])),
            ],
        ];
    }

    public static function varsayilan(): array
    {
        return self::anahtarlar();
    }

    /**
     * @return list<string>
     */
    public static function kullaniciIcin(?Kullanici $kullanici): array
    {
        if (! $kullanici) {
            return self::varsayilan();
        }

        $tercih = KullaniciKolonTercihi::query()
            ->where('kullanici_id', $kullanici->id)
            ->where('tablo_anahtari', 'tasinmazlar')
            ->first();

        if (! $tercih || empty($tercih->kolonlar) || ! is_array($tercih->kolonlar)) {
            return self::varsayilan();
        }

        $secilen = array_values(array_intersect($tercih->kolonlar, self::anahtarlar()));

        return $secilen !== [] ? $secilen : self::varsayilan();
    }
}
