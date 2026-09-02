<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bagimsiz_bolumler', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tasinmaz_id')->constrained('tasinmazlar')->cascadeOnDelete();

            $table->string('blok_no', 20)->nullable();
            $table->string('kat_no', 10);
            $table->string('bagimsiz_bolum_no', 20);
            $table->string('nitelik', 50);
            $table->decimal('brut_alan', 10, 2)->nullable();
            $table->decimal('net_alan', 10, 2)->nullable();
            $table->string('oda_sayisi', 10)->nullable();
            $table->string('cephe', 50)->nullable();

            $table->string('muhasebe_niteligi', 100)->nullable();
            $table->string('kayit_turu', 50)->nullable();
            $table->string('mevcut_kullanim_sekli', 100)->nullable();
            $table->string('isgal_durumu', 20)->nullable();
            $table->boolean('meclis_satis_karari_var')->default(false);
            $table->text('aciklama')->nullable();

            $table->timestamps();

            $table->unique(['tasinmaz_id', 'blok_no', 'bagimsiz_bolum_no'], 'bagimsiz_bolumler_tasinmaz_blok_bbn_unique');
            $table->index('nitelik');
            $table->index('meclis_satis_karari_var');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bagimsiz_bolumler');
    }
};
