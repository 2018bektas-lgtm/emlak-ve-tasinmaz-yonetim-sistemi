<?php

namespace App\Enums;

/** Kira dosyasına eklenen belgenin türü (referans: "Dökümante Alanı"). */
enum KiraBelgeTuru: string
{
    case EncumenKarari = 'encumen_karari';
    case KiraSozlesmesi = 'kira_sozlesmesi';
    case Yazisma = 'yazisma';
    case Tebligat = 'tebligat';
    case IhaleEvraki = 'ihale_evraki';
    case Kroki = 'kroki';
    case Diger = 'diger';

    public function etiket(): string
    {
        return match ($this) {
            self::EncumenKarari => 'Encümen Kararı',
            self::KiraSozlesmesi => 'Kira Sözleşmesi',
            self::Yazisma => 'Yazışmalar',
            self::Tebligat => 'Tebligat',
            self::IhaleEvraki => 'İhale Evrakı',
            self::Kroki => 'Kroki / Plan',
            self::Diger => 'Diğer',
        };
    }
}
