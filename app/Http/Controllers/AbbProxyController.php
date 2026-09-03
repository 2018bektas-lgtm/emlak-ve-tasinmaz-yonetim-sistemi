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

        return response($govde, 200, [
            'Content-Type' => $tip ?: 'image/png',
            'Cache-Control' => 'public, max-age=300',
            'Access-Control-Allow-Origin' => '*',
        ]);
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
