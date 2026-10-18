<?php

namespace App\Enums;

/**
 * Hisse satış sürecinin aşama enum'u. Aşama elle seçilmez; kayıtlardan
 * (tebligat, görüş/imar, encümen, satış tebligatı, ödeme, tapu) otomatik
 * hesaplanır — bkz. App\Support\HisseSureci::durumlariYenile().
 * Elle verilebilen tek durum "Reddedildi"dir.
 */
enum HisseBasvuruDurumu: string
{
    case Basvuruldu = 'basvuruldu';
    case TebligatAsamasinda = 'tebligat_asamasinda';
    case DegerlemeRaporuBekleniyor = 'degerleme_raporu_bekleniyor';
    case EncumenKarariBekleniyor = 'encumen_karari_bekleniyor';
    case SatisTebligatiBekleniyor = 'satis_tebligati_bekleniyor';
    case OdemeBekleniyor = 'odeme_bekleniyor';
    case TapuAsamasinda = 'tapu_asamasinda';
    case Tamamlandi = 'tamamlandi';
    case Reddedildi = 'reddedildi';

    public function etiket(): string
    {
        return match ($this) {
            self::Basvuruldu => 'Başvuruldu',
            self::TebligatAsamasinda => 'Tebligat Aşamasında',
            self::DegerlemeRaporuBekleniyor => 'Değerleme Raporu Bekleniyor',
            self::EncumenKarariBekleniyor => 'Encümen Kararı Bekleniyor',
            self::SatisTebligatiBekleniyor => 'Satış Tebligatı Bekleniyor',
            self::OdemeBekleniyor => 'Ödeme Bekleniyor',
            self::TapuAsamasinda => 'Tapu Aşamasında',
            self::Tamamlandi => 'Tamamlandı',
            self::Reddedildi => 'Reddedildi',
        };
    }

    public function pill(): string
    {
        return match ($this) {
            self::Basvuruldu => 'tl-pill-info',
            self::TebligatAsamasinda => 'tl-pill-warn',
            self::DegerlemeRaporuBekleniyor => 'tl-pill-warn',
            self::EncumenKarariBekleniyor => 'tl-pill-warn',
            self::SatisTebligatiBekleniyor => 'tl-pill-warn',
            self::OdemeBekleniyor => 'tl-pill-warn',
            self::TapuAsamasinda => 'tl-pill-info',
            self::Tamamlandi => 'tl-pill-ok',
            self::Reddedildi => 'tl-pill-danger',
        };
    }

    /**
     * UI'de select açıldığında sıralı liste.
     *
     * @return array<int, self>
     */
    public static function siralanmis(): array
    {
        return [
            self::Basvuruldu,
            self::TebligatAsamasinda,
            self::DegerlemeRaporuBekleniyor,
            self::EncumenKarariBekleniyor,
            self::SatisTebligatiBekleniyor,
            self::OdemeBekleniyor,
            self::TapuAsamasinda,
            self::Tamamlandi,
            self::Reddedildi,
        ];
    }
}
