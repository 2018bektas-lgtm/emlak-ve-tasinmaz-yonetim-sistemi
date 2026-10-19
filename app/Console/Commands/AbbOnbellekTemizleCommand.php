<?php

namespace App\Console\Commands;

use App\Http\Controllers\AbbProxyController;
use Illuminate\Console\Command;

/**
 * Ankara BB CBS kare önbelleğini budar.
 *
 * AbbProxyController her kareyi public/abb-onbellek/{katman}/{z}/{y}/{x}.png
 * olarak yazar; gezildikçe dosya sayısı büyür. Web sunucusu kareyi dosyadan
 * doğrudan verdiği için yaş kontrolü burada yapılır: silinen kare bir sonraki
 * istekte ABB'den tazesi alınır. Bu komut iki ölçüte göre budar:
 *   1) --gun  : bu yaştan eski dosyalar silinir (varsayılan: controller TTL'i)
 *   2) --mb   : kalan boyut hâlâ sınırın üstündeyse en eski dosyalar silinir
 */
class AbbOnbellekTemizleCommand extends Command
{
    protected $signature = 'abb:onbellek-temizle
        {--gun=30 : Bu gün sayısından eski dosyaları sil (abb:onbellek-isit tazesini indirir)}
        {--mb=0 : Budama sonrası izin verilen azami toplam boyut (MB; 0 = sınırsız)}
        {--kuru : Hiçbir şey silme, yalnızca ne silineceğini raporla}';

    protected $description = 'ABB CBS görüntü önbelleğini yaşa ve toplam boyuta göre budar';

    public function handle(): int
    {
        $kok = public_path(AbbProxyController::ONBELLEK_KOK);

        if (! is_dir($kok)) {
            $this->info('Önbellek dizini yok, yapacak bir şey kalmadı.');

            return self::SUCCESS;
        }

        $gun = max(0, (int) $this->option('gun'));
        $azamiBayt = (int) round(max(0, (float) $this->option('mb')) * 1024 * 1024);
        $kuru = (bool) $this->option('kuru');

        $dosyalar = $this->dosyalariTopla($kok);

        if ($dosyalar === []) {
            $this->info('Önbellek boş.');

            return self::SUCCESS;
        }

        $baslangicSayi = count($dosyalar);
        $baslangicBoyut = array_sum(array_column($dosyalar, 'boyut'));

        $silinen = 0;
        $kazanilan = 0;

        // 1) Yaşa göre
        $sinir = time() - $gun * 86400;
        foreach ($dosyalar as $i => $d) {
            if ($d['zaman'] < $sinir) {
                if ($kuru || @unlink($d['yol'])) {
                    $silinen++;
                    $kazanilan += $d['boyut'];
                    unset($dosyalar[$i]);
                }
            }
        }

        // 2) Boyuta göre — en eskiden başlayarak
        $kalanBoyut = $baslangicBoyut - $kazanilan;
        if ($azamiBayt > 0 && $kalanBoyut > $azamiBayt) {
            usort($dosyalar, fn ($a, $b) => $a['zaman'] <=> $b['zaman']);
            foreach ($dosyalar as $d) {
                if ($kalanBoyut <= $azamiBayt) {
                    break;
                }
                if ($kuru || @unlink($d['yol'])) {
                    $silinen++;
                    $kazanilan += $d['boyut'];
                    $kalanBoyut -= $d['boyut'];
                }
            }
        }

        if (! $kuru) {
            $this->bosDizinleriSil($kok);
        }

        $this->info(sprintf(
            '%sÖnbellek: %d dosya / %s  →  %d dosya / %s  (silinen: %d, kazanılan: %s)',
            $kuru ? '[KURU ÇALIŞMA] ' : '',
            $baslangicSayi,
            $this->okunur($baslangicBoyut),
            $baslangicSayi - $silinen,
            $this->okunur($baslangicBoyut - $kazanilan),
            $silinen,
            $this->okunur($kazanilan),
        ));

        return self::SUCCESS;
    }

    /** @return list<array{yol: string, boyut: int, zaman: int}> */
    private function dosyalariTopla(string $kok): array
    {
        $sonuc = [];
        $gezgin = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($kok, \FilesystemIterator::SKIP_DOTS));

        foreach ($gezgin as $dosya) {
            $yol = $dosya->getPathname();
            if (str_ends_with($yol, '.tmp')) {
                // Yarım kalmış geçici yazımlar (1 saatten eski)
                if ($dosya->getMTime() < time() - 3600) {
                    @unlink($yol);
                }

                continue;
            }
            if (str_ends_with($yol, '.png')) {
                $sonuc[] = ['yol' => $yol, 'boyut' => $dosya->getSize(), 'zaman' => $dosya->getMTime()];
            }
        }

        return $sonuc;
    }

    /** Boşalan alt dizinleri (en derinden başlayarak) siler; kök kalır. */
    private function bosDizinleriSil(string $kok): void
    {
        $gezgin = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($kok, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($gezgin as $oge) {
            if ($oge->isDir()) {
                @rmdir($oge->getPathname()); // dolu dizinde sessizce başarısız olur
            }
        }
    }

    private function okunur(int $bayt): string
    {
        if ($bayt >= 1024 * 1024) {
            return round($bayt / 1024 / 1024, 1).' MB';
        }
        if ($bayt >= 1024) {
            return round($bayt / 1024).' KB';
        }

        return $bayt.' B';
    }
}
