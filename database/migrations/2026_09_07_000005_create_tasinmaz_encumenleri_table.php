<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hisse satış grubuna ait Encümen kararı: satış bedeli/karar_no/kayıt_no
        // ve gelen-giden evraklar. grup_no ile başvuru grubuna bağlıdır.
        Schema::create('tasinmaz_encumenleri', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grup_no')->index(); // hisse_basvurulari.grup_no ile aynı
            $table->foreignId('tasinmaz_id')->constrained('tasinmazlar')->cascadeOnDelete();
            $table->string('karar_no', 100)->nullable();
            $table->string('kayit_no', 100)->nullable();
            $table->decimal('birim_fiyat', 15, 2)->nullable(); // ₺/m²
            $table->date('gelen_tarih')->nullable();
            $table->date('giden_tarih')->nullable();
            $table->string('gelen_evrak', 500)->nullable();
            $table->string('giden_evrak', 500)->nullable();
            $table->text('aciklama')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasinmaz_encumenleri');
    }
};
