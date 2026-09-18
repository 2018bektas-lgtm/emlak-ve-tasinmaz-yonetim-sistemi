<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecrimisil_isgalciler', function (Blueprint $table) {
            // GeoJSON Polygon veya [[lng,lat],...] dizisi
            $table->json('koordinat')->nullable()->after('nitelik')
                ->comment('İşgal alanının poligon geometrisi');
            $table->decimal('lat', 10, 7)->nullable()->after('koordinat');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
        });
    }

    public function down(): void
    {
        Schema::table('ecrimisil_isgalciler', function (Blueprint $table) {
            $table->dropColumn(['koordinat', 'lat', 'lng']);
        });
    }
};
