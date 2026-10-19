<?php

use App\Models\TasinmazKoordinat;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Haritalar yalnızca ekranda görünen alanı yükler: her geometrinin sınır kutusu
 * (min/max lat-lng) ayrı sütunlarda tutulur ve indexlenir. Ayrıca liste/özet
 * sayımlarının kullandığı mülkiyet süzgeci için kapsayan index eklenir.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tekrar çalıştırılabilir: sütun/index zaten varsa atlanır
        if (! Schema::hasColumn('tasinmaz_koordinatlar', 'min_lat')) {
            Schema::table('tasinmaz_koordinatlar', function (Blueprint $table) {
                $table->decimal('min_lat', 10, 7)->nullable()->after('lng');
                $table->decimal('max_lat', 10, 7)->nullable()->after('min_lat');
                $table->decimal('min_lng', 10, 7)->nullable()->after('max_lat');
                $table->decimal('max_lng', 10, 7)->nullable()->after('min_lng');
                $table->decimal('merkez_lat', 10, 7)->nullable()->after('max_lng');
                $table->decimal('merkez_lng', 10, 7)->nullable()->after('merkez_lat');
                $table->index(['min_lat', 'max_lat', 'min_lng', 'max_lng'], 'tasinmaz_koordinatlar_sinir_index');
                $table->index(['merkez_lat', 'merkez_lng'], 'tasinmaz_koordinatlar_merkez_index');
            });
        }

        if (! Schema::hasIndex('tasinmaz_hisseler', 'tasinmaz_hisseler_mulkiyet_index')) {
            Schema::table('tasinmaz_hisseler', function (Blueprint $table) {
                // Mülkiyet (tam/hisseli) süzgeci tablonun kendisine dokunmadan yalnızca index'ten okunur
                $table->index(['tasinmaz_id', 'hisse_durum', 'hisse_pay', 'hisse_payda'], 'tasinmaz_hisseler_mulkiyet_index');
            });
        }

        // Mevcut kayıtların sınır kutusunu doldur
        TasinmazKoordinat::query()->orderBy('id')->chunkById(1000, function ($kayitlar) {
            foreach ($kayitlar as $k) {
                $sinir = $k->sinirKutusu();
                if ($sinir) {
                    DB::table('tasinmaz_koordinatlar')->where('id', $k->id)->update($sinir);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('tasinmaz_hisseler', function (Blueprint $table) {
            $table->dropIndex('tasinmaz_hisseler_mulkiyet_index');
        });

        Schema::table('tasinmaz_koordinatlar', function (Blueprint $table) {
            $table->dropIndex('tasinmaz_koordinatlar_sinir_index');
            $table->dropIndex('tasinmaz_koordinatlar_merkez_index');
            $table->dropColumn(['min_lat', 'max_lat', 'min_lng', 'max_lng', 'merkez_lat', 'merkez_lng']);
        });
    }
};
