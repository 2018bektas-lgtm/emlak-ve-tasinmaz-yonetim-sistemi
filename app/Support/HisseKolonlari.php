<?php

namespace App\Support;

use App\Models\Kullanici;
use App\Models\KullaniciKolonTercihi;

class HisseKolonlari
{
    public static function katalog(): array
    {
        return [
            'ilce' => 'İlçe',
            'mahalle' => 'Mahalle',
            'ada' => 'Ada',
            'parsel' => 'Parsel',
            'nitelik' => 'Nitelik',
            'hisse_no' => 'Hisse No',
            'pay_payda' => 'Pay / Payda',
            'oran' => 'Oran %',
            'hisse_m2' => 'Hisse m²',
            'parsel_m2' => 'Parsel m²',
            'hisse_adedi' => 'Hisse adedi',
            'edinme' => 'Edinme',
            'tarih' => 'Tarih',
            'yevmiye' => 'Yevmiye',
            'islem' => 'İşlem Tipi',
            'durum' => 'Durum',
            'rayic' => 'Rayiç ₺',
            'il' => 'İl',
            'aktif_hisse' => 'Aktif hisse',
            'maliyet' => 'Maliyet ₺',
            'emlak_vd' => 'Emlak VD ₺',
            'iz_bedeli' => 'İz Bedeli ₺',
            'cikis' => 'Kayıtlardan Çıkış',
            'cikis_tarihi' => 'Çıkış Tarihi',
        ];
    }

    public static function anahtarlar(): array
    {
        return array_keys(self::katalog());
    }

    /**
     * @return array<string, array{ikon:string, kolonlar:array<string,string>}>
     */
    public static function gruplar(): array
    {
        $k = self::katalog();

        return [
            'Konum' => [
                'ikon' => 'pin',
                'kolonlar' => array_intersect_key($k, array_flip(['il', 'ilce', 'mahalle', 'ada', 'parsel', 'nitelik', 'parsel_m2'])),
            ],
            'Hisse' => [
                'ikon' => 'ruler',
                'kolonlar' => array_intersect_key($k, array_flip(['hisse_no', 'pay_payda', 'oran', 'hisse_m2', 'hisse_adedi', 'aktif_hisse', 'durum'])),
            ],
            'Edinme' => [
                'ikon' => 'file',
                'kolonlar' => array_intersect_key($k, array_flip(['edinme', 'tarih', 'yevmiye', 'islem', 'cikis', 'cikis_tarihi'])),
            ],
            'Bedel' => [
                'ikon' => 'layers',
                'kolonlar' => array_intersect_key($k, array_flip(['rayic', 'maliyet', 'emlak_vd', 'iz_bedeli'])),
            ],
        ];
    }

    public static function varsayilan(): array
    {
        return [
            'ilce', 'mahalle', 'ada', 'parsel', 'nitelik', 'hisse_no',
            'pay_payda', 'oran', 'hisse_m2', 'parsel_m2', 'hisse_adedi',
            'edinme', 'tarih', 'yevmiye', 'islem', 'durum', 'rayic',
        ];
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
            ->where('tablo_anahtari', 'hisseler')
            ->first();

        if (! $tercih || empty($tercih->kolonlar) || ! is_array($tercih->kolonlar)) {
            return self::varsayilan();
        }

        $secilen = array_values(array_intersect($tercih->kolonlar, self::anahtarlar()));

        return $secilen !== [] ? $secilen : self::varsayilan();
    }
}
