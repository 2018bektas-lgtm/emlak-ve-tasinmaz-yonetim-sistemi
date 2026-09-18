<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hissedara gönderilen ön tebligat (hisse satışı ilanı) — ilgili kişi
        // 15 gün içinde başvurmak zorunda. `grup_no` başvurudaki grup ile
        // eşleşir; hangi hissedarlara tebligat çıktığını kaydeder.
        Schema::create('hisse_tebligatlari', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grup_no')->index(); // hisse_basvurulari.grup_no ile aynı
            $table->foreignId('tasinmaz_id')->constrained('tasinmazlar')->cascadeOnDelete();
            $table->string('ad_soyad', 150);
            $table->string('tc_kimlik', 11);
            $table->decimal('tapu_hisse', 15, 2)->nullable();
            $table->date('tebligat_tarihi')->nullable();
            $table->date('ulastigi_tarihi')->nullable(); // Tebligatın hissedara ulaştığı tarih
            $table->boolean('basvurdu')->default(false); // Bu hissedar başvuru yaptı mı
            $table->boolean('tebligat_ulasmadi')->default(false); // Tebligat ulaşmadı işaretlemesi
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hisse_tebligatlari');
    }
};
