<?php

namespace App\Enums;

/** Kira dosyasını dayandıran karar / onay belgesi. */
enum KiraKararTuru: string
{
    case Encumen = 'encumen';
    case Meclis = 'meclis';
    case Olur = 'olur';
    case BaskanlikOnay = 'baskanlik_onay';
    case KurumYazisi = 'kurum_yazisi';
    case TalepYazisi = 'talep_yazisi';

    public function etiket(): string
    {
        return match ($this) {
            self::Encumen => 'Encümen Kararı',
            self::Meclis => 'Meclis Kararı',
            self::Olur => 'Olur',
            self::BaskanlikOnay => 'Başkanlık Onayı',
            self::KurumYazisi => 'Kurum Yazısı',
            self::TalepYazisi => 'Talep Yazısı',
        };
    }
}
