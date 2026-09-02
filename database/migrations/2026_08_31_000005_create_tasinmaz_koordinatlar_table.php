<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasinmaz_koordinatlar', function (Blueprint $table) {
            $table->id();

            // 1:1 iliski — tasinmaz basina tek koordinat kaydi.
            $table->foreignId('tasinmaz_id')->unique()->constrained('tasinmazlar')->cascadeOnDelete();

            // Parselin tam geometrisi (GeoJSON polygon / multipolygon).
            // Harita uzerinde parsel sinirlarini cizmek icin kullanilir.
            $table->json('koordinat')->nullable();

            // Harita marker'i ve bounds sorgusu icin ayri lat/lng.
            // decimal(10,7) ~ 1 cm hassasiyet — marker icin fazlasiyla yeterli.
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            $table->timestamps();

            // Harita viewport'unda "su bounds icindeki tasinmazlari getir"
            // sorgusu icin composite index.
            $table->index(['lat', 'lng']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasinmaz_koordinatlar');
    }
};
