<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bir taşınmaza yapılan hisse satış başvurusu (bir kişinin talebi).
        // Aynı taşınmaza birden çok başvuru gelebilir; hepsi bir "grup"ta
        // toplanır (grup_no = ilk başvurunun id'si) — böylece bir taşınmaz için
        // tüm başvurular tek bir dosya olarak yönetilir.
        Schema::create('hisse_basvurulari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tasinmaz_id')->constrained('tasinmazlar')->cascadeOnDelete();
            $table->unsignedBigInteger('grup_no')->nullable()->index(); // İlk başvurunun id'si (kendisi de dahil)
            $table->string('ad_soyad', 150);
            $table->string('tc_kimlik', 11);
            $table->string('gsm_no', 20)->nullable();
            $table->date('basvuru_tarihi');
            $table->decimal('tapu_hisse', 15, 2)->nullable(); // Başvuranın tapudaki hissesi (m²)
            $table->decimal('talep_edilen_hisse', 15, 2)->nullable(); // Talep m²
            $table->string('basvuru_evrak', 500)->nullable(); // PDF yolu
            $table->text('aciklama')->nullable();
            $table->enum('durum', [
                'basvuruldu',   // Başvuru alındı
                'incelemede',   // İnceleme aşamasında
                'onaylandi',    // Onaylandı, tebligat çıkarılabilir
                'reddedildi',   // Reddedildi
                'tamamlandi',   // Satış tamamlandı
            ])->default('basvuruldu')->index();
            $table->foreignId('mudurluk_id')->nullable()->constrained('mudurlukler')->nullOnDelete();
            $table->timestamps();

            $table->index(['tasinmaz_id', 'durum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hisse_basvurulari');
    }
};
