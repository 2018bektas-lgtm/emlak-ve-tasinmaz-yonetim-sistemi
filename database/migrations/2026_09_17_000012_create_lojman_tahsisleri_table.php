<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lojman_tahsisleri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lojman_id')->constrained('lojmanlar')->cascadeOnDelete();
            $table->foreignId('basvuru_id')->nullable()->constrained('lojman_basvurulari')->nullOnDelete();
            $table->string('ad_soyad', 200);
            $table->string('sicil_no', 50)->nullable();
            $table->date('baslangic_tarihi');
            $table->date('bitis_tarihi')->nullable()->comment('Boşsa devam ediyor');
            $table->string('karar_no', 100)->nullable();
            $table->date('karar_tarihi')->nullable();
            $table->text('aciklama')->nullable();
            $table->timestamps();

            $table->index('lojman_id');
            $table->index('basvuru_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lojman_tahsisleri');
    }
};
