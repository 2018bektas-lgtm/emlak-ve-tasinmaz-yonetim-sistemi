<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Ankara BB CBS proxy görüntü önbelleğini budar.
 *
 * AbbProxyController her (servis, katman, bbox, boyut) birleşimi için ayrı bir
 * PNG yazar. bbox serbest gezinmede sürekli değiştiğinden dosya sayısı zamanla
 * sınırsız büyür. Bu komut iki ölçüte göre budar:
 *   1) --gun  : bu yaştan eski dosyalar silinir (varsayılan: controller TTL'i)
 *   2) --mb   : kalan boyut hâlâ sınırın üstündeyse en eski dosyalar silinir
 */
class AbbOnbellekTemizleCommand extends Command
{
    protected $signature = 'abb:onbellek-temizle
        {--gun=7 : Bu gün sayısından eski dosyaları sil}
        {--mb=512 : Budama sonrası izin verilen azami toplam boyut (MB; 0 = sınırsız)}
        {--kuru : Hiçbir şey silme, yalnızca ne silineceğini raporla}';

    protected $description = 'ABB CBS görüntü önbelleğini yaşa ve toplam boyuta göre budar';

    public function handle(): int
    {
        $kok = storage_path('app/abb-onbellek');

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

        foreach (glob($kok.'/*', GLOB_ONLYDIR) ?: [] as $altDizin) {
            foreach (glob($altDizin.'/*.png') ?: [] as $yol) {
                $boyut = @filesize($yol);
                $zaman = @filemtime($yol);
                if ($boyut === false || $zaman === false) {
                    continue;
                }
                $sonuc[] = ['yol' => $yol, 'boyut' => $boyut, 'zaman' => $zaman];
            }

            // Yarım kalmış geçici yazımlar (1 saatten eski)
            foreach (glob($altDizin.'/*.tmp') ?: [] as $gecici) {
                if (@filemtime($gecici) < time() - 3600) {
                    @unlink($gecici);
                }
            }
        }

        return $sonuc;
    }

    private function bosDizinleriSil(string $kok): void
    {
        foreach (glob($kok.'/*', GLOB_ONLYDIR) ?: [] as $altDizin) {
            if ((glob($altDizin.'/*') ?: []) === []) {
                @rmdir($altDizin);
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
