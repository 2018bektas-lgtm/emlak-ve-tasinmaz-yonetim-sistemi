<?php

namespace Database\Seeders;

use App\Models\Kullanici;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Kullanici::query()->updateOrCreate(
            ['mail' => 'admin@etys.local'],
            [
                'ad' => 'Sistem',
                'soyad' => 'Yöneticisi',
                'kullanici_adi' => 'admin',
                'sifre' => 'password',
                'mail_dogrulama_tarihi' => now(),
            ]
        );

        $this->call([
            MuhasebeKayitSeeder::class,
            KayitTuruSeeder::class,
            ImarDurumuSeeder::class,
        ]);
    }
}
