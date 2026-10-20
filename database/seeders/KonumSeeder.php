<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * İl / ilçe / mahalle verisi TKGM cbsapi'den çekilir; işi tkgm:senkronize
 * komutu yapar, seeder yalnızca onu sarmalar. Veri zaten yüklüyse atlanır,
 * böylece db:seed tekrar çalıştırıldığında boşuna ağa çıkılmaz.
 *
 * Tam senkronizasyon ~970 ilçenin mahallelerini çeker ve birkaç dakika sürer.
 * Hızlı kurulum için .env'de KONUM_SEED_MAHALLE=false verilip yalnızca il ve
 * ilçeler alınabilir; mahalleler sonradan `php artisan tkgm:senkronize` ile
 * tamamlanır.
 */
class KonumSeeder extends Seeder
{
    public function run(): void
    {
        $mahalleIstensin = filter_var(env('KONUM_SEED_MAHALLE', true), FILTER_VALIDATE_BOOLEAN);

        if ($this->veriTam($mahalleIstensin)) {
            $this->command?->info('Konum verisi mevcut, TKGM senkronizasyonu atlandı.');

            return;
        }

        $this->command?->info('TKGM konum verisi çekiliyor (birkaç dakika sürebilir)...');

        $parametreler = $mahalleIstensin ? [] : ['--atla-mahalleler' => true];

        try {
            Artisan::call('tkgm:senkronize', $parametreler, $this->command?->getOutput());
        } catch (\Throwable $e) {
            // TKGM'ye ulaşılamaması tüm db:seed'i düşürmesin; diğer seeder'lar
            // çalışmış olur, konum verisi sonradan tamamlanabilir.
            $this->command?->warn('TKGM senkronizasyonu başarısız: '.$e->getMessage());
            $this->command?->warn('Sonra tekrar deneyin: php artisan db:seed --class=KonumSeeder');
        }
    }

    /** İl ve ilçeler dolu mu; mahalle isteniyorsa o da dolu mu. */
    private function veriTam(bool $mahalleIstensin): bool
    {
        $var = DB::table('iller')->exists() && DB::table('ilceler')->exists();

        return $mahalleIstensin
            ? $var && DB::table('mahalleler')->exists()
            : $var;
    }
}
