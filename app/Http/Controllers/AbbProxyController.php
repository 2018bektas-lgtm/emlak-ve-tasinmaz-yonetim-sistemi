<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Ankara BB CBS proxy köprüsü.
 *
 * abbcbs.ankara.bel.tr `?<upstream-url>` biçimi bekliyor; tarayıcı ve Esri SDK
 * tam URL'yi yeniden encode ettiği için doğrudan çağrı bozuluyor. Bu controller,
 * güvenli parametrelerden upstream URL'sini backend'de kurar ve cURL ile raw olarak
 * çağırır, cevabı image olarak istemciye döner. Böylece encoding sorunu ve
 * potansiyel CORS problemleri ortadan kalkar.
 */
class AbbProxyController extends Controller
{
    private const PROXY_BASE = 'https://abbcbs.ankara.bel.tr/api/Gis/Proxy';

    /** İzin verilen katman kimlikleri (blade/JS ile senkron). */
    private const IZIN_KATMAN = [0, 2, 3, 4];

    /** Disk önbelleği ömrü — sınır/yapı/numarataj verisi nadiren değişir. */
    private const ONBELLEK_GUN = 7;

    /** Tarayıcı önbelleği ömrü (saniye). */
    private const TARAYICI_SANIYE = 86400;

    public function katman(Request $request): Response
    {
        $service = (string) $request->query('service', '');
        $layer = (int) $request->query('layer', -1);
        $bbox = (string) $request->query('bbox', '');
        $size = (string) $request->query('size', '');

        // Servis GUID hash — base64 GUID uzunluğunda; sadece güvenli karakterler.
        if (! preg_match('/^[A-Za-z0-9_=-]{22,64}$/', $service)) {
            return response('Invalid service', 400);
        }
        if (! in_array($layer, self::IZIN_KATMAN, true)) {
            return response('Invalid layer', 400);
        }
        if (! preg_match('/^-?\d+(\.\d+)?,-?\d+(\.\d+)?,-?\d+(\.\d+)?,-?\d+(\.\d+)?$/', $bbox)) {
            return response('Invalid bbox', 400);
        }
        if (! preg_match('/^\d{1,5},\d{1,5}$/', $size)) {
            return response('Invalid size', 400);
        }

        // ---- Disk önbelleği: aynı görünüm tekrar istenirse upstream'e gitme ----
        $anahtar = sha1($service.'|'.$layer.'|'.$bbox.'|'.$size);
        $dosya = $this->onbellekYolu($anahtar);

        if (is_file($dosya) && filemtime($dosya) > time() - self::ONBELLEK_GUN * 86400) {
            $govde = file_get_contents($dosya);
            if ($govde !== false && $govde !== '') {
                return $this->gorselCevap($govde, $request, $anahtar, 'HIT');
            }
        }

        // Upstream URL — proxy `?` sonrası **raw** string bekliyor,
        // http_build_query bize doğru encoding yapar (sadece query-value'lar).
        $params = http_build_query([
            'bbox' => $bbox,
            'bboxSR' => 3857,
            'imageSR' => 3857,
            'size' => $size,
            'dpi' => 96,
            'format' => 'png32',
            'transparent' => 'true',
            'layers' => 'show:'.$layer,
            'f' => 'image',
        ]);
        $upstream = 'https://'.$service.'.gissrv.org/export?'.$params;
        $tamUrl = self::PROXY_BASE.'?'.$upstream;

        $ch = curl_init($tamUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'EmlakYonetim/1.0',
            CURLOPT_HTTPHEADER => [
                'Accept: image/png,image/*;q=0.9,*/*;q=0.1',
                'Referer: https://abbcbs.ankara.bel.tr/',
            ],
        ]);
        $govde = curl_exec($ch);
        $hata = curl_error($ch);
        $kod = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $tip = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($govde === false || $hata !== '') {
            return response('Upstream error: '.$hata, 502);
        }

        // Proxy başarısızlıkta "An error occured" text/plain döndürüyor —
        // istemciye şeffaf 1x1 PNG dön ki katman hata yerine boş görünsün.
        if ($kod !== 200 || stripos($tip, 'image/') !== 0) {
            return $this->bosPng();
        }

        // Başarılı görüntüyü diske yaz (hatalı/boş cevaplar önbelleğe girmez)
        $this->onbellegeYaz($dosya, $govde);

        return $this->gorselCevap($govde, $request, $anahtar, 'MISS');
    }

    /** Görüntü cevabı — ETag ile koşullu istek desteği. */
    private function gorselCevap(string $govde, Request $request, string $anahtar, string $durum): Response
    {
        $etag = '"'.$anahtar.'"';

        // Tarayıcı elindeki kopyayı soruyorsa gövdeyi tekrar göndermeyelim.
        if (trim((string) $request->header('If-None-Match')) === $etag) {
            return response('', 304, [
                'ETag' => $etag,
                'Cache-Control' => 'public, max-age='.self::TARAYICI_SANIYE,
                'X-Abb-Cache' => $durum,
            ]);
        }

        return response($govde, 200, [
            'Content-Type' => 'image/png',
            'Content-Length' => (string) strlen($govde),
            'ETag' => $etag,
            'Cache-Control' => 'public, max-age='.self::TARAYICI_SANIYE,
            'Access-Control-Allow-Origin' => '*',
            'X-Abb-Cache' => $durum,
        ]);
    }

    /** storage/app/abb-onbellek/ab/cdef... — tek dizinde binlerce dosya olmasın. */
    private function onbellekYolu(string $anahtar): string
    {
        return storage_path('app/abb-onbellek/'.substr($anahtar, 0, 2).'/'.$anahtar.'.png');
    }

    private function onbellegeYaz(string $dosya, string $govde): void
    {
        $dizin = dirname($dosya);
        if (! is_dir($dizin) && ! @mkdir($dizin, 0775, true) && ! is_dir($dizin)) {
            return; // önbellek yazılamazsa sessizce geç — istek yine de döner
        }

        // Aynı anahtarı iki istek birlikte yazarsa yarım dosya kalmasın.
        $gecici = $dosya.'.'.getmypid().'.tmp';
        if (@file_put_contents($gecici, $govde) !== false) {
            @rename($gecici, $dosya);
        }
    }

    private function bosPng(): Response
    {
        // 1×1 şeffaf PNG
        $data = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='
        );

        return response($data, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=60',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
}
