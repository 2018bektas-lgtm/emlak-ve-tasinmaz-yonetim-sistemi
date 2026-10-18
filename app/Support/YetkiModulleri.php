<?php

namespace App\Support;

/**
 * Rol ekranlarında izinlerin gruplandığı modüller. Her modül bir ya da
 * daha çok izin grubunu (izinler.grup) kapsar. Yeni bir izin grubu
 * eklenirse burada bir modüle bağlanmalıdır; bağlanmayan gruplar rol
 * formunda "Diğer" modülünde gösterilir.
 */
class YetkiModulleri
{
    /** @return array<string, array{ad:string, alt:string, renk:string, ikon:string, gruplar:array<string,string>}> */
    public static function liste(): array
    {
        return [
            'tasinmaz' => [
                'ad' => 'Taşınmaz Yönetimi',
                'alt' => 'Taşınmaz kayıtları, hisseleri, yapıları ve harita.',
                'renk' => '#2563eb',
                'ikon' => '<path d="M3 21h18M5 21V7l7-5 7 5v14"/><path d="M9 21v-6h6v6"/>',
                'gruplar' => ['tasinmaz' => 'Taşınmaz', 'hisse' => 'Hisse', 'yapi' => 'Yapı / Bağımsız Bölüm', 'harita' => 'Harita'],
            ],
            'degerleme' => [
                'ad' => 'Değerleme',
                'alt' => 'Taşınmaz (arsa / bağımsız bölüm) ve ecrimisil değerlemesi.',
                'renk' => '#0d9488',
                'ikon' => '<path d="M12 3v18M7 21h10M4 7h16"/><path d="M6 7l-3 7a3 3 0 0 0 6 0zM18 7l-3 7a3 3 0 0 0 6 0z"/>',
                'gruplar' => ['degerleme' => 'Taşınmaz Değerleme', 'ecrimisil' => 'Ecrimisil'],
            ],
            'satis' => [
                'ad' => 'Satış ve Kullanım',
                'alt' => 'Hisse satışı ve lojman.',
                'renk' => '#ea580c',
                'ikon' => '<circle cx="12" cy="12" r="9"/><path d="M15 9.5c-.5-1-1.5-1.5-3-1.5-1.7 0-3 1-3 2 0 2.5 6 1.5 6 4 0 1-1.3 2-3 2-1.5 0-2.5-.5-3-1.5M12 6v2M12 16v2"/>',
                'gruplar' => ['hisse-satisi' => 'Hisse Satışı', 'lojman' => 'Lojman'],
            ],
            'rapor' => [
                'ad' => 'Raporlama',
                'alt' => 'Sayıştay raporları ve genel rapor görüntüleme.',
                'renk' => '#7c3aed',
                'ikon' => '<path d="M4 20V10M10 20V4M16 20V13M22 20H2"/>',
                'gruplar' => ['rapor' => 'Raporlar'],
            ],
            'sistem' => [
                'ad' => 'Sistem Yönetimi',
                'alt' => 'Kullanıcılar, roller ve izinler.',
                'renk' => '#0f172a',
                'ikon' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c1-4 4-6 6.5-6s5.5 2 6.5 6"/><circle cx="17" cy="9" r="2.5"/><path d="M15 14c3 0 5 2 6 6"/>',
                'gruplar' => ['kullanici' => 'Kullanıcı', 'rol' => 'Rol ve İzin'],
            ],
        ];
    }

    /**
     * Veritabanındaki gruplardan hiçbir modüle bağlanmamış olanları "Diğer"
     * modülü olarak ekler (yeni izinler formda kaybolmasın).
     *
     * @param  iterable<string>  $mevcutGruplar
     */
    public static function tamListe(iterable $mevcutGruplar): array
    {
        $liste = self::liste();
        $bagli = collect($liste)->flatMap(fn ($m) => array_keys($m['gruplar']))->all();
        $bagsiz = collect($mevcutGruplar)->reject(fn ($g) => in_array($g, $bagli, true))->values();
        if ($bagsiz->isNotEmpty()) {
            $liste['diger'] = [
                'ad' => 'Diğer',
                'alt' => 'Henüz bir modüle bağlanmamış izinler.',
                'renk' => '#64748b',
                'ikon' => '<circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/>',
                'gruplar' => $bagsiz->mapWithKeys(fn ($g) => [$g => ucfirst($g)])->all(),
            ];
        }

        return $liste;
    }
}
