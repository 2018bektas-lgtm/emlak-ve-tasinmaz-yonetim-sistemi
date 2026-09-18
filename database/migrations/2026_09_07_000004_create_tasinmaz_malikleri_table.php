<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Taşınmazın tapudaki hissedar (malik) listesi.
        // Hisse satış tebligatlarında bu listeden çoklu seçim yapılır.
        Schema::create('tasinmaz_malikleri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tasinmaz_id')->constrained('tasinmazlar')->cascadeOnDelete();
            $table->string('ad_soyad', 150);
            $table->string('tc_kimlik', 11)->nullable();
            $table->decimal('tapu_hisse', 15, 2)->nullable(); // hissedarın tapudaki payı (m²)
            $table->string('adres', 500)->nullable();
            $table->string('gsm_no', 20)->nullable();
            $table->timestamps();

            $table->index(['tasinmaz_id', 'tc_kimlik']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasinmaz_malikleri');
    }
};
