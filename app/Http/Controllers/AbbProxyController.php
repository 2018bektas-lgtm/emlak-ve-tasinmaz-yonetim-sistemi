<?php

namespace App\Http\Controllers;

use App\Support\AbbKareDeposu;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Ankara BB CBS (baskentcbs) kare köprüsü.
 *
 * Adres, public/abb-onbellek altındaki dosyayla birebir aynıdır: kare
 * önbellekteyse web sunucusu dosyayı doğrudan verir ve bu controller hiç
 * çalışmaz. Yalnızca eksik kare için çağrılır.
 *
 * Eksik kare talep listesine yazılır. abb:onbellek-isit çalışıyorsa kareyi o
 * indirir (kalıcı bağlantılarla, acil talepler her şeyin önünde) ve bu istek
 * dosya oluşana kadar bekler. Her eksik kare için ayrı PHP isteğinin ABB'ye
 * yeni bağlantı açması Cloudflare'e takılıyordu (bir ekranda 50+ eksik kare →
 * 6–10 sn, kısmen yanıtsız; ölçüldü). Isıtıcı çalışmıyorsa kare burada
 * doğrudan indirilir.
 *
 * ABB 429 dönerse ENGEL_SANIYE boyunca ABB'ye gidilmez, hemen 503 dönülür
 * (istemci kareyi yeniden ister).
 */
class AbbProxyController extends Controller
{
    public const ONBELLEK_KOK = AbbKareDeposu::KOK;

    public const KATMANLAR = AbbKareDeposu::KATMANLAR;

    private const ENGEL_SANIYE = 20;

    private const ENGEL_ANAHTARI = 'abb-kare:engel';

    /** Isıtıcının kareyi indirmesi için en çok bekleme süresi (sn). */
    private const BEKLEME_SANIYE = 12;

    public function kare(AbbKareDeposu $depo, string $katman, int $z, int $y, int $x): Response
    {
        if (! AbbKareDeposu::gecerliMi($katman, $z, $y, $x)) {
            return response('Geçersiz kare', 400);
        }

        $buyutme = AbbKareDeposu::tur($katman) === 'tile' && $z > AbbKareDeposu::ABB_SEVIYE;
        if (! $buyutme && ! AbbKareDeposu::varMi($katman, $z, $y, $x)) {
            AbbKareDeposu::talepEt($katman, $z, $y, $x);

            if (AbbKareDeposu::isiticiCalisiyorMu()) {
                $govde = $this->isiticiyiBekle($katman, $z, $y, $x);

                return $govde === null ? $this->sonraDene() : $this->goruntu($govde);
            }
            if (Cache::has(self::ENGEL_ANAHTARI)) {
                return $this->sonraDene();
            }
        }

        [$govde, $kod] = $depo->getir($katman, $z, $y, $x);
        if ($govde === null) {
            if ($kod === 429) {
                Cache::put(self::ENGEL_ANAHTARI, true, self::ENGEL_SANIYE);
            }

            return $this->sonraDene();
        }

        return $this->goruntu($govde);
    }

    /** Isıtıcı kareyi dosyaya yazana kadar bekler; süre dolarsa null. */
    private function isiticiyiBekle(string $katman, int $z, int $y, int $x): ?string
    {
        $dosya = AbbKareDeposu::dosyaYolu($katman, $z, $y, $x);
        $bitis = microtime(true) + self::BEKLEME_SANIYE;
        while (microtime(true) < $bitis) {
            clearstatcache(true, $dosya);
            if (is_file($dosya) && ($govde = @file_get_contents($dosya)) !== false && $govde !== '') {
                return $govde;
            }
            usleep(40_000);
        }

        return null;
    }

    private function goruntu(string $govde): Response
    {
        return response($govde, 200, [
            'Content-Type' => str_starts_with($govde, "\xFF\xD8") ? 'image/jpeg' : 'image/png',
            'Cache-Control' => 'public, max-age=86400',
            'X-Abb-Cache' => 'MISS',
        ]);
    }

    private function sonraDene(): Response
    {
        return response('ABB sunucusu şu an yanıt vermiyor', 503, [
            'Retry-After' => '5',
            'Cache-Control' => 'no-store',
        ]);
    }
}
