<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lojman_basvurulari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lojman_id')->nullable()->constrained('lojmanlar')->nullOnDelete()
                ->comment('Belirli bir lojmana başvuru; boşsa herhangi bir lojman');
            $table->string('ad_soyad', 200);
            $table->string('tc_kimlik', 11)->nullable();
            $table->string('sicil_no', 50)->nullable();
            $table->string('unvan', 150)->nullable();
            $table->string('birim', 200)->nullable();
            $table->string('tahsis_turu', 40)->nullable()->comment('Sıra / Görev / Hizmet / Temsil');
            $table->date('basvuru_tarihi');
            $table->string('durum', 30)->default('basvuruldu')
                ->comment('basvuruldu | degerlendirmede | onaylandi | reddedildi | tahsisedildi');
            $table->text('aciklama')->nullable();
            $table->timestamps();

            $table->index('lojman_id');
            $table->index('tc_kimlik');
            $table->index('sicil_no');
            $table->index('durum');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lojman_basvurulari');
    }
};
