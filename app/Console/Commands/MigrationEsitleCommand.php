<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * migrations tablosunu veritabanındaki gerçek tablolarla eşitler.
 *
 * Migration dosyaları bir ara birleştirilip yeniden adlandırıldı. Eski adlarla
 * kurulmuş veritabanlarında (canlı sunucu) migrations tablosu yeni dosya
 * adlarını tanımadığından `php artisan migrate` var olan tabloları yeniden
 * oluşturmaya çalışıp düşüyor. Bu komut, bekleyen her migration için:
 *   - oluşturduğu tabloların hepsi zaten varsa → "çalıştı" olarak işaretler
 *   - hiçbiri yoksa → bekletir (migrate oluşturur)
 *   - bir kısmı yoksa → eksikleri başka bekleyen bir migration oluşturuyorsa
 *     işaretler, aksi halde dokunmaz ve raporlar
 *   - yalnızca tablo değiştiriyorsa (Schema::table) → bekletir (migrate çalıştırır)
 * Hiçbir tabloyu silmez ya da değiştirmez; yalnızca migrations tablosuna satır ekler.
 */
class MigrationEsitleCommand extends Command
{
    protected $signature = 'migration:esitle {--kuru : Yalnızca ne yapılacağını göster}';

    protected $description = 'Tabloları zaten var olan bekleyen migrationları çalıştı olarak işaretler (eski adlarla kurulmuş veritabanları için)';

    public function handle(): int
    {
        if (! Schema::hasTable('migrations')) {
            $this->error('migrations tablosu yok; önce php artisan migrate:install');

            return self::FAILURE;
        }

        $calisan = DB::table('migrations')->pluck('migration')->all();
        $dosyalar = glob(database_path('migrations/*.php'));
        sort($dosyalar);

        $bekleyen = [];
        foreach ($dosyalar as $dosya) {
            $ad = basename($dosya, '.php');
            if (! in_array($ad, $calisan, true)) {
                $icerik = (string) file_get_contents($dosya);
                preg_match_all("/Schema::create\\(\\s*'([a-z0-9_]+)'/", $icerik, $m);
                $bekleyen[$ad] = array_values(array_unique($m[1]));
            }
        }

        if ($bekleyen === []) {
            $this->info('Bekleyen migration yok.');

            return self::SUCCESS;
        }

        // Başka bir bekleyen migration'ın oluşturacağı tablolar
        $olusturulacak = [];
        foreach ($bekleyen as $ad => $tablolar) {
            foreach ($tablolar as $t) {
                $olusturulacak[$t][] = $ad;
            }
        }

        $isaretlenecek = [];
        $satirlar = [];
        foreach ($bekleyen as $ad => $tablolar) {
            if ($tablolar === []) {
                $satirlar[] = [$ad, 'bekletilir', 'tablo değiştiriyor; migrate çalıştıracak'];

                continue;
            }
            $eksik = array_values(array_filter($tablolar, fn ($t) => ! Schema::hasTable($t)));
            if ($eksik === []) {
                $isaretlenecek[] = $ad;
                $satirlar[] = [$ad, 'işaretlenir', 'tabloların hepsi var: '.implode(', ', $tablolar)];
            } elseif (count($eksik) === count($tablolar)) {
                $satirlar[] = [$ad, 'bekletilir', 'tablolar yok, migrate oluşturacak: '.implode(', ', $tablolar)];
            } else {
                $baskasi = array_filter($eksik, fn ($t) => count(array_diff($olusturulacak[$t], [$ad])) > 0);
                if (count($baskasi) === count($eksik)) {
                    $isaretlenecek[] = $ad;
                    $satirlar[] = [$ad, 'işaretlenir', 'eksik ('.implode(', ', $eksik).') başka migration ile oluşacak'];
                } else {
                    $satirlar[] = [$ad, 'DOKUNULMADI', 'kısmen var; eksik: '.implode(', ', $eksik).' — elle incelenmeli'];
                }
            }
        }

        $this->table(['Migration', 'İşlem', 'Neden'], $satirlar);

        if ($this->option('kuru')) {
            $this->warn('Kuru çalışma: hiçbir şey yazılmadı.');

            return self::SUCCESS;
        }

        if ($isaretlenecek !== []) {
            $parti = (int) DB::table('migrations')->max('batch') + 1;
            DB::table('migrations')->insert(array_map(fn ($ad) => ['migration' => $ad, 'batch' => $parti], $isaretlenecek));
        }
        $this->info(count($isaretlenecek).' migration çalıştı olarak işaretlendi. Şimdi: php artisan migrate');

        return self::SUCCESS;
    }
}
