<?php

namespace App\Support;

/**
 * Ankara BB CBS (baskentcbs) harita karesi deposu.
 *
 * Kareler public/abb-onbellek/{katman}/{z}/{y}/{x}.png olarak tutulur; dosya
 * varsa web sunucusu doğrudan verir (PHP çalışmaz, ~2 ms). Eksik kareleri
 * AbbProxyController (kullanıcı istediğinde) ve abb:onbellek-isit komutu
 * (arka planda, önceden) bu sınıfla indirir.
 *
 * baskentcbs Cloudflare arkasında: paralel ya da HTTP/2 isteklere 429 / bot
 * doğrulaması dönüyor; tek bağlantı üzerinden sıralı HTTP/1.1 istekler ise
 * sorunsuz (~165 ms/kare, ölçüldü). İndirici bu yüzden tek bağlantıyı açık tutar.
 *
 * Kare şeması standart XYZ (EPSG:3857).
 *   tile   önbellekli servis, ABB'nin hazır karesi, 256 px. ABB'de 19'a kadar
 *          var; 20–22, 19'daki üst kareden kesilip büyütülür.
 *   export dinamik servis, 512 px çizilir: z karesi ekranda z+1 zoomuna denk.
 */
final class AbbKareDeposu
{
    public const KOK = 'abb-onbellek';

    private const BASKENTCBS = 'https://baskentcbs.ankara.bel.tr/server/rest/services';

    public const DUNYA = 40075016.685578488;

    private const YARI = 20037508.342789244;

    private const EXPORT_KARE = 512;

    /** baskentcbs önbelleğinin son seviyesi (20+ → 404). */
    public const ABB_SEVIYE = 19;

    public const AZAMI_ZOOM = ['tile' => 22, 'export' => 21];

    /**
     * Katmanlar: kısa ad → [tür, servis yolu, export katman no, [ısıtılacak en az z, en çok z]].
     * Isıtma aralığı katmanın ABB'de çizildiği ölçeklere göre (export z = ekran zoom − 1):
     * numarataj 1:1.500'den, yapı 1:5.000'den yakın; mahalle 1:10.000–1:150.000; ilçe 1:50.000'den uzak.
     */
    public const KATMANLAR = [
        'imar' => ['tile', 'plan/uip_esri3', null, [10, 19]],
        'parselasyon' => ['tile', 'ortak/ParselAktifCached', null, [12, 19]],
        'belediye' => ['export', 'tasinmaz/tasinmazservice', 0, [13, 19]],
        'numarataj' => ['export', 'ortak/adres', 0, [18, 20]],
        'yapi' => ['export', 'ortak/adres', 2, [16, 19]],
        'mahalle' => ['export', 'ortak/adres', 3, [11, 15]],
        'ilce' => ['export', 'ortak/adres', 4, [9, 12]],
    ];

    /** Kullanıcının önbellekte bulamadığı kareler (ısıtıcı önce bunların çevresini doldurur). */
    private const TALEP_DOSYASI = 'app/abb-talep.txt';

    /** @var \CurlHandle|null tek, açık tutulan bağlantı */
    private $baglanti = null;

    /** @var \CurlMultiHandle|null toplu indirme (bağlantılar açık kalır) */
    private $coklu = null;

    /** @var array<int, \CurlHandle> toplu indirmede yeniden kullanılan bağlantılar */
    private array $havuz = [];

    public static function tur(string $katman): string
    {
        return self::KATMANLAR[$katman][0];
    }

    public static function dosyaYolu(string $katman, int $z, int $y, int $x): string
    {
        return public_path(self::KOK.'/'.$katman.'/'.$z.'/'.$y.'/'.$x.'.png');
    }

    public static function varMi(string $katman, int $z, int $y, int $x): bool
    {
        return is_file(self::dosyaYolu($katman, $z, $y, $x));
    }

    public static function gecerliMi(string $katman, int $z, int $y, int $x): bool
    {
        if (! isset(self::KATMANLAR[$katman]) || $z < 0 || $z > self::AZAMI_ZOOM[self::tur($katman)]) {
            return false;
        }
        $n = 2 ** $z;

        return $x >= 0 && $y >= 0 && $x < $n && $y < $n;
    }

    /**
     * Kareyi önbellekten ya da ABB'den getirir ve diske yazar.
     *
     * @return array{0: ?string, 1: int} [görüntü (geçici hatada null), ABB HTTP kodu (önbellekten 0)]
     */
    public function getir(string $katman, int $z, int $y, int $x): array
    {
        $dosya = self::dosyaYolu($katman, $z, $y, $x);
        if (is_file($dosya)) {
            return [file_get_contents($dosya) ?: null, 0];
        }
        if (self::tur($katman) === 'tile' && $z > self::ABB_SEVIYE) {
            return $this->buyut($katman, $z, $y, $x);
        }

        [$govde, $kod] = $this->indir($this->kaynakUrl($katman, $z, $y, $x));
        if ($govde !== null) {
            self::yaz($dosya, $govde);
        }

        return [$govde, $kod];
    }

    /** Isıtıcı çalışırken bu dosyaya düzenli dokunur (AbbProxyController kareyi ondan bekler). */
    private const NABIZ_DOSYASI = 'app/abb-isitici.nabiz';

    public static function nabizAt(): void
    {
        @touch(storage_path(self::NABIZ_DOSYASI));
    }

    public static function isiticiCalisiyorMu(): bool
    {
        $yol = storage_path(self::NABIZ_DOSYASI);
        clearstatcache(true, $yol);

        return is_file($yol) && filemtime($yol) >= time() - 15;
    }

    /** Kullanıcının bulamadığı kareyi talep listesine ekler. */
    public static function talepEt(string $katman, int $z, int $y, int $x): void
    {
        @file_put_contents(storage_path(self::TALEP_DOSYASI), "$katman $z $y $x\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * Talep listesini okuyup boşaltır.
     *
     * @return list<array{0: string, 1: int, 2: int, 3: int}>
     */
    public static function talepleriAl(): array
    {
        $yol = storage_path(self::TALEP_DOSYASI);
        if (! is_file($yol) || ! ($f = @fopen($yol, 'c+'))) {
            return [];
        }
        flock($f, LOCK_EX);
        $icerik = stream_get_contents($f);
        ftruncate($f, 0);
        flock($f, LOCK_UN);
        fclose($f);

        $talepler = [];
        foreach (array_unique(array_filter(explode("\n", (string) $icerik))) as $satir) {
            $p = explode(' ', trim($satir));
            if (count($p) === 4 && self::gecerliMi($p[0], (int) $p[1], (int) $p[2], (int) $p[3])) {
                $talepler[] = [$p[0], (int) $p[1], (int) $p[2], (int) $p[3]];
            }
        }

        return $talepler;
    }

    /**
     * Birden çok kareyi kalıcı bağlantı havuzuyla paralel indirir (ısıtıcı için).
     * baskentcbs 3–4 kalıcı bağlantıya izin veriyor, 6'da 429 dönüyor (ölçüldü).
     *
     * @param  list<array{0: string, 1: int, 2: int, 3: int}>  $kareler
     * @return list<array{0: ?string, 1: int}> aynı sırayla [görüntü, HTTP kodu]
     */
    public function topluGetir(array $kareler): array
    {
        $this->coklu ??= curl_multi_init();
        $sonuc = [];
        $bekleyen = [];
        foreach ($kareler as $i => [$katman, $z, $y, $x]) {
            if (self::tur($katman) === 'tile' && $z > self::ABB_SEVIYE) {
                $sonuc[$i] = $this->getir($katman, $z, $y, $x);

                continue;
            }
            $ch = $this->havuz[$i] ??= curl_init();
            $this->ayarla($ch, $this->kaynakUrl($katman, $z, $y, $x));
            curl_multi_add_handle($this->coklu, $ch);
            $bekleyen[$i] = $ch;
        }

        do {
            $durum = curl_multi_exec($this->coklu, $calisan);
            if ($calisan) {
                curl_multi_select($this->coklu, 1.0);
            }
        } while ($calisan && $durum === CURLM_OK);

        foreach ($bekleyen as $i => $ch) {
            $sonuc[$i] = $this->yanit($ch, curl_multi_getcontent($ch));
            curl_multi_remove_handle($this->coklu, $ch);
            [$katman, $z, $y, $x] = $kareler[$i];
            if ($sonuc[$i][0] !== null) {
                self::yaz(self::dosyaYolu($katman, $z, $y, $x), $sonuc[$i][0]);
            }
        }
        ksort($sonuc);

        return array_values($sonuc);
    }

    /** Boylam/enlem → kare (x, y). */
    public static function kareNo(float $lng, float $lat, int $z): array
    {
        $n = 2 ** $z;
        $lat = max(-85.05, min(85.05, $lat));
        $x = (int) floor(($lng + 180) / 360 * $n);
        $y = (int) floor((1 - log(tan(deg2rad($lat)) + 1 / cos(deg2rad($lat))) / M_PI) / 2 * $n);

        return [max(0, min($n - 1, $x)), max(0, min($n - 1, $y))];
    }

    private function kaynakUrl(string $katman, int $z, int $y, int $x): string
    {
        [$tur, $yol, $katmanNo] = self::KATMANLAR[$katman];
        $taban = self::BASKENTCBS.'/'.$yol.'/MapServer';
        if ($tur === 'tile') {
            return $taban.'/tile/'.$z.'/'.$y.'/'.$x;
        }

        $w = self::DUNYA / (2 ** $z);
        $xmin = -self::YARI + $x * $w;
        $ymax = self::YARI - $y * $w;

        return $taban.'/export?'.http_build_query([
            'bbox' => sprintf('%.2f,%.2f,%.2f,%.2f', $xmin, $ymax - $w, $xmin + $w, $ymax),
            'bboxSR' => 3857,
            'imageSR' => 3857,
            'size' => self::EXPORT_KARE.','.self::EXPORT_KARE,
            'dpi' => 96,
            'format' => 'png32',
            'transparent' => 'true',
            'layers' => 'show:'.$katmanNo,
            'f' => 'image',
        ]);
    }

    /**
     * Tek, açık tutulan bağlantı üzerinden indirir.
     *
     * @return array{0: ?string, 1: int} [görüntü (kare yoksa boş PNG; geçici hatada null), HTTP kodu]
     */
    private function indir(string $url): array
    {
        $this->baglanti ??= curl_init();
        $this->ayarla($this->baglanti, $url);

        return $this->yanit($this->baglanti, curl_exec($this->baglanti));
    }

    /** @param \CurlHandle $ch */
    private function ayarla($ch, string $url): void
    {
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'EmlakYonetim/1.0',
            CURLOPT_HTTPHEADER => ['Accept: image/png,image/*;q=0.9,*/*;q=0.1'],
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_TCP_KEEPALIVE => 1,
        ]);
    }

    /**
     * @param  \CurlHandle  $ch
     * @return array{0: ?string, 1: int} [görüntü (kare yoksa boş PNG; geçici hatada null), HTTP kodu]
     */
    private function yanit($ch, string|false|null $govde): array
    {
        $kod = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $tip = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

        if ($govde === false || $govde === null || $govde === '' || $kod === 0 || $kod === 429 || $kod >= 500) {
            return [null, $kod];
        }
        if ($kod === 200 && stripos($tip, 'image/') === 0) {
            return [$govde, $kod];
        }

        // Kare yok (önbellek dışı alan, 404): boş kare — o da önbelleğe girer
        return [self::bosPng(), $kod];
    }

    /** ABB seviyesinin üstündeki kare: 19'daki üst kareden ilgili parça kesilip 256 px'e büyütülür. */
    private function buyut(string $katman, int $z, int $y, int $x): array
    {
        $fark = $z - self::ABB_SEVIYE;
        [$ust, $kod] = $this->getir($katman, self::ABB_SEVIYE, $y >> $fark, $x >> $fark);
        if ($ust === null) {
            return [null, $kod];
        }

        $kaynak = @imagecreatefromstring($ust);
        if (! $kaynak || imagesx($kaynak) < 256) {
            $govde = self::bosPng(); // üst kare boş: alt kare de boş
        } else {
            $parca = 256 >> $fark;
            $hedef = imagecreatetruecolor(256, 256);
            imagealphablending($hedef, false);
            imagesavealpha($hedef, true);
            imagefill($hedef, 0, 0, imagecolorallocatealpha($hedef, 0, 0, 0, 127));
            imagecopyresampled($hedef, $kaynak, 0, 0, ($x % (1 << $fark)) * $parca, ($y % (1 << $fark)) * $parca, 256, 256, $parca, $parca);
            ob_start();
            imagepng($hedef, null, 6);
            $govde = (string) ob_get_clean();
            imagedestroy($hedef);
        }
        if ($kaynak) {
            imagedestroy($kaynak);
        }
        self::yaz(self::dosyaYolu($katman, $z, $y, $x), $govde);

        return [$govde, $kod];
    }

    private static function yaz(string $dosya, string $govde): void
    {
        $dizin = dirname($dosya);
        if (! is_dir($dizin) && ! @mkdir($dizin, 0775, true) && ! is_dir($dizin)) {
            return; // önbellek yazılamazsa sessizce geç
        }

        // Aynı kareyi iki işlem birlikte yazarsa yarım dosya kalmasın.
        $gecici = $dosya.'.'.getmypid().'.tmp';
        if (@file_put_contents($gecici, $govde) !== false && ! @rename($gecici, $dosya)) {
            @unlink($gecici);
        }
    }

    /** 1×1 şeffaf PNG */
    public static function bosPng(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
    }
}
