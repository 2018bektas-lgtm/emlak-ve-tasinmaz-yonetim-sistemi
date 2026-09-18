<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecrimisil_raporlari', function (Blueprint $table) {
            $table->dropColumn('baslik');
            $table->decimal('fiyat', 15, 2)->nullable()->after('rapor_tarihi')
                ->comment('Rapor bedeli (₺)');
        });
    }

    public function down(): void
    {
        Schema::table('ecrimisil_raporlari', function (Blueprint $table) {
            $table->dropColumn('fiyat');
            $table->string('baslik', 300)->nullable()->after('rapor_tarihi');
        });
    }
};
