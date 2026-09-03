<?php

namespace Database\Seeders;

use App\Models\Mudurluk;
use Illuminate\Database\Seeder;

class MudurlukSeeder extends Seeder
{
    public function run(): void
    {
        $kayitlar = [
            ['kod' => 'emlak-istimlak', 'ad' => 'Emlak ve İstimlak Müdürlüğü', 'sira' => 1],
            ['kod' => 'imar-sehircilik', 'ad' => 'İmar ve Şehircilik Müdürlüğü', 'sira' => 2],
            ['kod' => 'fen-isleri', 'ad' => 'Fen İşleri Müdürlüğü', 'sira' => 3],
            ['kod' => 'park-bahceler', 'ad' => 'Park ve Bahçeler Müdürlüğü', 'sira' => 4],
        ];

        foreach ($kayitlar as $kayit) {
            Mudurluk::query()->updateOrCreate(
                ['kod' => $kayit['kod']],
                [
                    'ad' => $kayit['ad'],
                    'aktif_mi' => true,
                    'sira' => $kayit['sira'],
                ]
            );
        }

        $this->command?->info('MudurlukSeeder: '.Mudurluk::query()->count().' müdürlük hazır.');
    }
}
