<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class TkgmSenkronizeCommand extends Command
{
    protected $signature = 'tkgm:senkronize
        {--il= : Sadece belirtilen TKGM il id icin ilce+mahalle senkronize et}
        {--atla-iller : Iller tablosunu atla}
        {--atla-mahalleler : Mahalle senkronizasyonunu atla}
        {--paralel=8 : Ayni anda gonderilecek istek sayisi}';

    protected $description = 'TKGM cbsapi uzerinden il, ilce ve mahalle listesini senkronize eder';

    private const BASE_URL = 'https://cbsapi.tkgm.gov.tr/megsiswebapi.v3.1/api/idariYapi';

    private const CHUNK_SIZE = 500;

    /** @var array<int, string> Ag hatasi nedeniyle atlanan kayitlar */
    private array $atlananlar = [];

    public function handle(): int
    {
        $paralel = max(1, (int) $this->option('paralel'));
        $tekTkgmIlId = $this->option('il') !== null ? (int) $this->option('il') : null;

        if ($tekTkgmIlId === null && ! $this->option('atla-iller')) {
            $this->illeriSenkronizeEt();
        }

        // TKGM il id -> yerel id map'i
        $ilQuery = DB::table('iller')->select('id', 'tkgm_id');
        if ($tekTkgmIlId !== null) {
            $ilQuery->where('tkgm_id', $tekTkgmIlId);
        }
        $ilMap = $ilQuery->pluck('id', 'tkgm_id')->all(); // [tkgm_id => yerel_id]

        if (empty($ilMap)) {
            $this->error('Iller tablosu bos veya belirtilen il bulunamadi. Once iller senkronize edilmeli.');

            return self::FAILURE;
        }

        $ilceMap = $this->ilceleriSenkronizeEt($ilMap, $paralel);

        if (! $this->option('atla-mahalleler')) {
            $this->mahalleleriSenkronizeEt($ilceMap, $paralel);
        }

        $this->newLine();
        $this->info(sprintf(
            'Ozet: %d il, %d ilce, %d mahalle',
            DB::table('iller')->count(),
            DB::table('ilceler')->count(),
            DB::table('mahalleler')->count(),
        ));

        if ($this->atlananlar !== []) {
            $this->warn(sprintf(
                '%d kayit ag hatasi nedeniyle atlandi. Komutu tekrar calistirarak tamamlayabilirsiniz: %s',
                count($this->atlananlar),
                implode(', ', array_slice($this->atlananlar, 0, 12)),
            ));
        }

        return self::SUCCESS;
    }

    private function illeriSenkronizeEt(): void
    {
        $this->info('Iller cekiliyor...');

        $response = Http::timeout(60)->retry(3, 500)->get(self::BASE_URL.'/illiste');
        $features = $this->ozellikleriCikart($response);
        if ($features === null) {
            throw new \RuntimeException('TKGM il listesi alinamadi.');
        }

        $simdi = now();
        $satirlar = array_map(
            fn (array $prop) => [
                'tkgm_id' => (int) $prop['id'],
                'ad' => (string) $prop['text'],
                'created_at' => $simdi,
                'updated_at' => $simdi,
            ],
            $features
        );

        $this->topluUpsert('iller', $satirlar, ['tkgm_id'], ['ad', 'updated_at']);
        $this->info(sprintf('  %d il yazildi.', count($satirlar)));
    }

    /**
     * @param  array<int, int>  $ilMap  [tkgm_il_id => yerel_il_id]
     * @return array<int, int>          [tkgm_ilce_id => yerel_ilce_id]
     */
    private function ilceleriSenkronizeEt(array $ilMap, int $paralel): array
    {
        $this->info('Ilceler cekiliyor...');
        $tkgmIlIdleri = array_keys($ilMap);
        $bar = $this->output->createProgressBar(count($tkgmIlIdleri));
        $bar->start();

        foreach (array_chunk($tkgmIlIdleri, $paralel) as $chunk) {
            $yanitlar = Http::pool(fn (Pool $pool) => array_map(
                fn (int $tkgmIlId) => $pool->as((string) $tkgmIlId)
                    ->timeout(60)
                    ->retry(3, 500)
                    ->get(self::BASE_URL.'/ilceListe/'.$tkgmIlId),
                $chunk
            ));

            $simdi = now();
            $satirlar = [];
            foreach ($chunk as $tkgmIlId) {
                $features = $this->ozellikleriCikartVeyaTekrarDene(
                    $yanitlar[(string) $tkgmIlId],
                    self::BASE_URL.'/ilceListe/'.$tkgmIlId,
                    60
                );
                if ($features === null) {
                    $this->atlananlar[] = 'il '.$tkgmIlId;
                    $bar->advance();
                    continue;
                }
                foreach ($features as $prop) {
                    $satirlar[] = [
                        'tkgm_id' => (int) $prop['id'],
                        'il_id' => $ilMap[$tkgmIlId],
                        'ad' => (string) $prop['text'],
                        'created_at' => $simdi,
                        'updated_at' => $simdi,
                    ];
                }
                $bar->advance();
            }

            if ($satirlar !== []) {
                $this->topluUpsert('ilceler', $satirlar, ['tkgm_id'], ['il_id', 'ad', 'updated_at']);
            }
        }

        $bar->finish();
        $this->newLine();

        // Yazdiktan sonra kendi id'lerimizi map olarak topla
        return DB::table('ilceler')
            ->whereIn('il_id', array_values($ilMap))
            ->pluck('id', 'tkgm_id')
            ->all();
    }

    /**
     * @param  array<int, int>  $ilceMap  [tkgm_ilce_id => yerel_ilce_id]
     */
    private function mahalleleriSenkronizeEt(array $ilceMap, int $paralel): void
    {
        $tkgmIlceIdleri = array_keys($ilceMap);
        $this->info(sprintf('Mahalleler cekiliyor (%d ilce)...', count($tkgmIlceIdleri)));
        $bar = $this->output->createProgressBar(count($tkgmIlceIdleri));
        $bar->start();

        foreach (array_chunk($tkgmIlceIdleri, $paralel) as $chunk) {
            $yanitlar = Http::pool(fn (Pool $pool) => array_map(
                fn (int $tkgmIlceId) => $pool->as((string) $tkgmIlceId)
                    ->timeout(90)
                    ->retry(3, 750)
                    ->get(self::BASE_URL.'/mahalleListe/'.$tkgmIlceId),
                $chunk
            ));

            $simdi = now();
            $satirlar = [];
            foreach ($chunk as $tkgmIlceId) {
                $features = $this->ozellikleriCikartVeyaTekrarDene(
                    $yanitlar[(string) $tkgmIlceId],
                    self::BASE_URL.'/mahalleListe/'.$tkgmIlceId,
                    90
                );
                if ($features === null) {
                    $this->atlananlar[] = 'ilce '.$tkgmIlceId;
                    $bar->advance();
                    continue;
                }
                foreach ($features as $prop) {
                    $satirlar[] = [
                        'tkgm_id' => (int) $prop['id'],
                        'ilce_id' => $ilceMap[$tkgmIlceId],
                        'ad' => (string) $prop['text'],
                        'created_at' => $simdi,
                        'updated_at' => $simdi,
                    ];
                }
                $bar->advance();
            }

            foreach (array_chunk($satirlar, self::CHUNK_SIZE) as $altChunk) {
                $this->topluUpsert('mahalleler', $altChunk, ['tkgm_id'], ['ilce_id', 'ad', 'updated_at']);
            }
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Havuzdan bos donen istegi tek seferlik senkron olarak yeniden dener.
     *
     * @param  mixed  $havuzYaniti
     * @return array<array{id: int|string, text: string}>|null
     */
    private function ozellikleriCikartVeyaTekrarDene($havuzYaniti, string $url, int $timeout): ?array
    {
        $sonuc = $this->ozellikleriCikart($havuzYaniti);
        if ($sonuc !== null) {
            return $sonuc;
        }

        try {
            return $this->ozellikleriCikart(
                Http::timeout($timeout)->retry(2, 1000)->get($url)
            );
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Havuz sonucu Response yerine ConnectionException de olabilir; tek bir ag
     * kesintisi tum senkronizasyonu durdurmasin diye null donulur.
     *
     * @param  mixed  $response
     * @return array<array{id: int|string, text: string}>|null
     */
    private function ozellikleriCikart($response): ?array
    {
        if (! $response instanceof Response || $response->failed()) {
            return null;
        }

        $features = $response->json('features') ?? [];
        $sonuc = [];

        foreach ($features as $feature) {
            $prop = $feature['properties'] ?? null;
            if (! is_array($prop) || ! isset($prop['id'], $prop['text'])) {
                continue;
            }
            $sonuc[] = $prop;
        }

        return $sonuc;
    }

    /**
     * @param  array<int, array<string, mixed>>  $satirlar
     * @param  array<int, string>                $benzersizAnahtar
     * @param  array<int, string>                $guncellenecek
     */
    private function topluUpsert(string $tablo, array $satirlar, array $benzersizAnahtar, array $guncellenecek): void
    {
        if ($satirlar === []) {
            return;
        }

        DB::table($tablo)->upsert($satirlar, $benzersizAnahtar, $guncellenecek);
    }
}
