<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecrimisil_raporlari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isgalci_id')->constrained('ecrimisil_isgalciler')->cascadeOnDelete();
            $table->string('rapor_no', 100)->nullable();
            $table->date('rapor_tarihi')->nullable();
            $table->string('baslik', 300)->nullable()->comment('Rapor başlığı / konusu');
            $table->string('dosya_yolu', 500)->nullable()->comment('PDF veya Word ek');
            $table->text('aciklama')->nullable();
            $table->timestamps();

            $table->index('isgalci_id');
            $table->index('rapor_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecrimisil_raporlari');
    }
};
