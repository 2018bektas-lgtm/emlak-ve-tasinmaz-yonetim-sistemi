<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecrimisil_isgalciler', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tasinmaz_id')->nullable()->constrained('tasinmazlar')->nullOnDelete();
            $table->foreignId('kullanici_id')->nullable()->constrained('kullanicilar')->nullOnDelete()
                ->comment('İşgal takibi için atanan kullanıcı');
            $table->foreignId('mudurluk_id')->nullable()->constrained('mudurlukler')->nullOnDelete();

            $table->string('ad_soyad_unvan', 200)->comment('Gerçek kişi ad-soyad veya tüzel kişi ünvan');
            $table->string('tc_vergi_no', 20)->nullable()->comment('TC (11) veya Vergi No (10)');
            $table->string('telefon', 30)->nullable();
            $table->string('adres', 500)->nullable();
            $table->text('aciklama')->nullable();

            $table->timestamps();

            $table->index('tasinmaz_id');
            $table->index('kullanici_id');
            $table->index('ad_soyad_unvan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecrimisil_isgalciler');
    }
};
