<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kira modülünün kod (lookup) tabloları. Referans sistemde bunlar ana tablonun
 * içinde serbest metin sütunlarıydı; ayrı tabloya alınınca hem süzgeçler
 * indexten okunuyor hem de aynı değerin farklı yazımları birikmiyor.
 */
return new class extends Migration
{
    public function up(): void
    {
        // İş kolu: kafeterya, otopark, taksi durağı, büfe...
        Schema::create('kira_is_kollari', function (Blueprint $table) {
            $table->id();
            $table->string('ad', 150)->unique();
            $table->string('kod', 30)->nullable();
            $table->unsignedSmallInteger('sira')->default(0);
            $table->boolean('aktif_mi')->default(true)->index();
            $table->timestamps();
        });

        // Hukuki durum: dava var, icra, tahliye davası...
        Schema::create('kira_hukuki_durumlar', function (Blueprint $table) {
            $table->id();
            $table->string('ad', 150)->unique();
            $table->text('aciklama')->nullable();
            $table->unsignedSmallInteger('sira')->default(0);
            $table->boolean('aktif_mi')->default(true)->index();
            $table->timestamps();
        });

        // Kiracılık durumu: devam ediyor, devredildi, feshedildi...
        Schema::create('kira_kiracilik_durumlari', function (Blueprint $table) {
            $table->id();
            $table->string('ad', 150)->unique();
            $table->unsignedSmallInteger('sira')->default(0);
            $table->boolean('aktif_mi')->default(true)->index();
            $table->timestamps();
        });

        // Muhasebe niteliği (detay formunda zorunlu alan)
        Schema::create('kira_muhasebe_nitelikleri', function (Blueprint $table) {
            $table->id();
            $table->string('ad', 150)->unique();
            $table->string('kod', 30)->nullable();
            $table->unsignedSmallInteger('sira')->default(0);
            $table->boolean('aktif_mi')->default(true)->index();
            $table->timestamps();
        });

        /**
         * TÜFE oranları: kira artışı bu orandan hesaplanır. Oran ay bazında
         * yayımlandığı için (yıl, ay) benzersiz; hesaplama sırasında tek
         * sorguyla okunur, bedel satırına kopyalanır.
         */
        Schema::create('kira_tufe_oranlari', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('yil');
            $table->unsignedTinyInteger('ay');
            $table->decimal('oran', 6, 3)->comment('On iki aylık ortalama % değişim');
            $table->text('aciklama')->nullable();
            $table->timestamps();

            $table->unique(['yil', 'ay']);
        });
    }

    public function down(): void
    {
        foreach ([
            'kira_tufe_oranlari',
            'kira_muhasebe_nitelikleri',
            'kira_kiracilik_durumlari',
            'kira_hukuki_durumlar',
            'kira_is_kollari',
        ] as $tablo) {
            Schema::dropIfExists($tablo);
        }
    }
};
