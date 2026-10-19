<?php

namespace App\Console\Commands;

use App\Models\TasinmazKoordinat;
use App\Support\AbbKareDeposu;
use Generator;
use Illuminate\Console\Command;

/**
 * ABB CBS kare önbelleğini önceden doldurur; harita kareleri kullanıcı
 * istemeden diske iner ve web sunucusundan milisaniyede gelir.
 *
 * Öncelik sırası:
 *   0) acil     kullanıcının önbellekte bulamadığı kareler: her gruptan önce
 *               indirilir (AbbProxyController talep listesine yazar ve bekler)
 *   1) talep    bu karelerin çevresi (aynı seviye ±3, bir alt seviye)
 *   2) tasinmaz sistemdeki her taşınmazın çevresi (250 m), tüm katmanlar, z 15–19
 *   3) alan     Ankara merkezi, imar + parselasyon; şehir merkezinden dışa
 *               doğru halka halka, seviye seviye (--alan-zoom)
 *
 * ABB'ye 3 kalıcı bağlantıyla istek atılır (Cloudflare her istekte yeni
 * bağlantıya ya da 6+ paralel bağlantıya 429 dönüyor). 429 gelirse beklenir. Bitirilen alan seviyeleri durum dosyasına
 * yazılır; komut her dakika zamanlanmış çalıştığında baştan taramaz.
 */
class AbbOnbellekIsitCommand extends Command
{
    protected $signature = 'abb:onbellek-isit
        {--sure=0 : En fazla bu kadar saniye çalış (0 = iş bitene kadar)}
        {--surekli : İş bitince kapanma, yeni talepleri bekle}
        {--alan=32.55,39.78,33.10,40.08 : Isıtılacak alan (minBoylam,minEnlem,maksBoylam,maksEnlem)}
        {--alan-zoom=10-18 : Alan için seviye aralığı}
        {--sadece= : Yalnızca bu aşama (talep|tasinmaz|alan)}
        {--sifirla : Alan ilerleme durumunu sil, alanı baştan tara}';

    protected $description = 'ABB CBS harita karelerini önceden indirir (taleper, taşınmaz çevreleri, Ankara merkezi)';

    /** Ankara merkezi (Kızılay): alan taraması buradan dışa doğru ilerler. */
    private const MERKEZ = [32.8541, 39.9208];

    /** Taşınmaz çevresinde indirilecek yarıçap (m). */
    private const TASINMAZ_YARICAP = 250;

    /** Talep edilen karenin çevresinde aynı seviyede indirilecek halka. */
    private const TALEP_HALKA = 3;

    private const DURUM_DOSYASI = 'app/abb-isit-durum.json';

    /** Aynı anda açık kalıcı bağlantı. baskentcbs IP başına ~5–6 kare/sn kaldırıyor; 4+ bağlantıda ara ara 429 (ölçüldü). */
    private const PARALEL = 3;

    private AbbKareDeposu $depo;

    private float $bitis = INF;

    private array $sayac = ['indirilen' => 0, 'var' => 0, 'bos' => 0, 'hata' => 0, 'engel' => 0];

    private float $baslangic;

    private float $sonRapor = 0;

    /** @var list<array{0: string, 1: int, 2: int, 3: int}> çevresi doldurulacak talepler */
    private array $cevreKuyrugu = [];

    private bool $cevreIsleniyor = false;

    public function handle(AbbKareDeposu $depo): int
    {
        $this->depo = $depo;
        $this->baslangic = microtime(true);
        $sure = (int) $this->option('sure');
        if ($sure > 0) {
            $this->bitis = $this->baslangic + $sure;
        }
        if ($this->option('sifirla')) {
            @unlink(storage_path(self::DURUM_DOSYASI));
        }
        $sadece = (string) $this->option('sadece');
        AbbKareDeposu::nabizAt();

        $asamalar = [
            'talep' => fn () => $this->cevreKareleri(),
            'tasinmaz' => fn () => $this->tasinmazKareleri(),
            'alan' => fn () => $this->alanKareleri(),
        ];

        foreach ($asamalar as $ad => $uret) {
            if ($sadece !== '' && $sadece !== $ad) {
                continue;
            }
            $this->line("<info>▶ {$ad}</info>");
            if (! $this->acilTalepler() || ! $this->isle($uret())) {
                return $this->bitir();
            }
        }

        // Sürekli modda yeni talepleri bekle
        while ($this->option('surekli') && ! $this->sureDoldu()) {
            AbbKareDeposu::nabizAt();
            if (! $this->acilTalepler() || ! $this->isle($this->cevreKareleri())) {
                break;
            }
            usleep(100_000);
        }

        return $this->bitir();
    }

    /**
     * Kareleri PARALEL'lik gruplar halinde indirir; süre dolarsa false.
     * Her gruptan önce acil talepler indirilir; bekleyen çevre işi varsa önce o yapılır.
     */
    private function isle(iterable $kareler): bool
    {
        $grup = [];
        $gorulen = [];
        foreach ($kareler as $kare) {
            if ($this->sureDoldu()) {
                return false;
            }
            $anahtar = implode('/', $kare);
            if (isset($gorulen[$anahtar]) || AbbKareDeposu::varMi(...$kare)) {
                $this->sayac['var']++;

                continue;
            }
            $gorulen[$anahtar] = true;
            $grup[] = $kare;
            if (count($grup) < self::PARALEL) {
                continue;
            }
            if (! $this->araIs() || ! $this->grubuIndir($grup)) {
                return false;
            }
            $grup = [];
            $this->rapor();
        }

        return $grup === [] || ($this->araIs() && $this->grubuIndir($grup));
    }

    /** Gruplar arasında: nabız, acil talepler ve (uzun bir aşamadaysak) bekleyen çevre işi. */
    private function araIs(): bool
    {
        AbbKareDeposu::nabizAt();
        if (! $this->acilTalepler()) {
            return false;
        }
        if ($this->cevreKuyrugu !== [] && ! $this->cevreIsleniyor) {
            $this->cevreIsleniyor = true;
            $tamam = $this->isle($this->cevreKareleri());
            $this->cevreIsleniyor = false;

            return $tamam;
        }

        return true;
    }

    /**
     * Kullanıcının istediği kareler (AbbProxyController bunları bekliyor): hemen indirilir.
     * Çevreleri sonra doldurulmak üzere kuyruğa alınır (en yeni talep önce).
     */
    private function acilTalepler(): bool
    {
        while (($talepler = AbbKareDeposu::talepleriAl()) !== []) {
            $this->cevreKuyrugu = array_merge($talepler, $this->cevreKuyrugu);
            $eksik = array_values(array_filter($talepler, fn ($t) => ! AbbKareDeposu::varMi(...$t)));
            foreach (array_chunk($eksik, self::PARALEL) as $grup) {
                if (! $this->grubuIndir($grup)) {
                    return false;
                }
            }
            $this->rapor();
        }

        return true;
    }

    /** Bir grubu indirir; 429'da bekleyip yeniden dener. Süre dolarsa false. */
    private function grubuIndir(array $grup): bool
    {
        while ($grup !== []) {
            $tekrar = [];
            foreach ($this->depo->topluGetir($grup) as $i => [$govde, $kod]) {
                if ($govde !== null) {
                    $this->sayac[$kod === 200 || $kod === 0 ? 'indirilen' : 'bos']++;
                } elseif ($kod === 429) {
                    $tekrar[] = $grup[$i];
                } else {
                    $this->sayac['hata']++; // bir sonraki turda tekrar denenir
                }
            }
            if ($tekrar === []) {
                return true;
            }
            $this->sayac['engel']++;
            $this->warn('  ABB 429 döndü, 15 sn bekleniyor…');
            for ($i = 0; $i < 15; $i++) {
                AbbKareDeposu::nabizAt();
                sleep(1);
            }
            if ($this->sureDoldu()) {
                return false;
            }
            $grup = $tekrar;
        }

        return true;
    }

    /** Talep edilen karelerin aynı seviyedeki çevresi ve bir alt seviyedeki karşılığı. */
    private function cevreKareleri(): Generator
    {
        while (($t = array_shift($this->cevreKuyrugu)) !== null) {
            [$katman, $z, $y, $x] = $t;
            yield from $this->halka($katman, $z, $x, $y, self::TALEP_HALKA);
            if ($z + 1 <= AbbKareDeposu::KATMANLAR[$katman][3][1]) {
                yield from $this->halka($katman, $z + 1, 2 * $x, 2 * $y, 2);
            }
        }
    }

    /** Her taşınmazın çevresi: tüm katmanlar, katmanın ısıtma aralığı ∩ z 15–19. */
    private function tasinmazKareleri(): Generator
    {
        $noktalar = TasinmazKoordinat::query()->whereNotNull('merkez_lat')
            ->get(['merkez_lat', 'merkez_lng'])
            ->map(fn ($k) => [(float) $k->merkez_lng, (float) $k->merkez_lat]);
        $this->line('  '.$noktalar->count().' taşınmaz');

        foreach (AbbKareDeposu::KATMANLAR as $katman => [$tur, , , [$zmin, $zmax]]) {
            for ($z = max(15, $zmin); $z <= min(19, $zmax); $z++) {
                foreach ($noktalar as [$lng, $lat]) {
                    [$x, $y] = AbbKareDeposu::kareNo($lng, $lat, $z);
                    $genislik = AbbKareDeposu::DUNYA / (2 ** $z) * cos(deg2rad($lat));
                    yield from $this->halka($katman, $z, $x, $y, (int) ceil(self::TASINMAZ_YARICAP / $genislik));
                }
            }
        }
    }

    /** Ankara merkezi: imar + parselasyon, seviye seviye, merkezden dışa halka halka. */
    private function alanKareleri(): Generator
    {
        [$minLng, $minLat, $maxLng, $maxLat] = array_map('floatval', explode(',', (string) $this->option('alan')));
        [$zBas, $zSon] = array_map('intval', explode('-', (string) $this->option('alan-zoom')));
        $durum = $this->durumOku();

        for ($z = $zBas; $z <= $zSon; $z++) {
            foreach (['imar', 'parselasyon'] as $katman) {
                [, , , [$zmin, $zmax]] = AbbKareDeposu::KATMANLAR[$katman];
                $anahtar = "$katman:$z";
                if ($z < $zmin || $z > $zmax || ! empty($durum[$anahtar])) {
                    continue;
                }
                [$x1, $y1] = AbbKareDeposu::kareNo($minLng, $maxLat, $z);
                [$x2, $y2] = AbbKareDeposu::kareNo($maxLng, $minLat, $z);
                [$mx, $my] = AbbKareDeposu::kareNo(self::MERKEZ[0], self::MERKEZ[1], $z);
                $azami = max($mx - $x1, $x2 - $mx, $my - $y1, $y2 - $my);
                $this->line(sprintf('  %s z=%d: %d kare', $katman, $z, ($x2 - $x1 + 1) * ($y2 - $y1 + 1)));

                for ($r = 0; $r <= $azami; $r++) {
                    foreach ($this->halkaKenari($mx, $my, $r) as [$x, $y]) {
                        if ($x >= $x1 && $x <= $x2 && $y >= $y1 && $y <= $y2) {
                            yield [$katman, $z, $y, $x];
                        }
                    }
                }
                // Buraya ulaşıldıysa seviye bitti (süre dolsaydı üretici yarıda kalırdı)
                $durum[$anahtar] = date('c');
                $this->durumYaz($durum);
            }
        }
    }

    /** (x, y) merkezli, r yarıçaplı karenin tüm kareleri (içten dışa). */
    private function halka(string $katman, int $z, int $x, int $y, int $r): Generator
    {
        for ($i = 0; $i <= $r; $i++) {
            foreach ($this->halkaKenari($x, $y, $i) as [$xx, $yy]) {
                if (AbbKareDeposu::gecerliMi($katman, $z, $yy, $xx)) {
                    yield [$katman, $z, $yy, $xx];
                }
            }
        }
    }

    /** Merkeze Chebyshev uzaklığı tam r olan kareler. */
    private function halkaKenari(int $mx, int $my, int $r): Generator
    {
        if ($r === 0) {
            yield [$mx, $my];

            return;
        }
        for ($x = $mx - $r; $x <= $mx + $r; $x++) {
            yield [$x, $my - $r];
            yield [$x, $my + $r];
        }
        for ($y = $my - $r + 1; $y <= $my + $r - 1; $y++) {
            yield [$mx - $r, $y];
            yield [$mx + $r, $y];
        }
    }

    private function sureDoldu(): bool
    {
        return microtime(true) >= $this->bitis;
    }

    private function rapor(bool $zorla = false): void
    {
        if (! $zorla && microtime(true) - $this->sonRapor < 10) {
            return;
        }
        $this->sonRapor = microtime(true);
        $gecen = max(1, microtime(true) - $this->baslangic);
        $this->line(sprintf(
            '  indirilen %d · boş %d · zaten var %d · hata %d · 429 %d · %.1f kare/sn',
            $this->sayac['indirilen'], $this->sayac['bos'], $this->sayac['var'], $this->sayac['hata'], $this->sayac['engel'],
            ($this->sayac['indirilen'] + $this->sayac['bos']) / $gecen,
        ));
    }

    private function bitir(): int
    {
        $this->rapor(true);

        return self::SUCCESS;
    }

    private function durumOku(): array
    {
        $yol = storage_path(self::DURUM_DOSYASI);

        return is_file($yol) ? (json_decode((string) file_get_contents($yol), true) ?: []) : [];
    }

    private function durumYaz(array $durum): void
    {
        file_put_contents(storage_path(self::DURUM_DOSYASI), json_encode($durum, JSON_PRETTY_PRINT));
    }
}
