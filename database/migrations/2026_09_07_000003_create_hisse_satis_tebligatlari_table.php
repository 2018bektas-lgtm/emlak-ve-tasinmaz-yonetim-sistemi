<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Onaylanan başvurular için hazırlanan satış tebligatı — bedel bildirimi
        // ve ödeme takibi burada tutulur.
        Schema::create('hisse_satis_tebligatlari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('basvuru_id')->constrained('hisse_basvurulari')->cascadeOnDelete();
            $table->unsignedBigInteger('grup_no')->index();
            $table->date('tebligat_tarihi');
            $table->date('ulastigi_tarihi')->nullable();
            $table->decimal('hisseye_dusen_yuzolcum', 15, 2);
            $table->decimal('birim_fiyat', 15, 2)->nullable();
            $table->decimal('toplam_bedel', 15, 2);
            $table->boolean('odedi')->default(false);
            $table->boolean('tebligat_ulasmadi')->default(false);
            $table->text('aciklama')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hisse_satis_tebligatlari');
    }
};
