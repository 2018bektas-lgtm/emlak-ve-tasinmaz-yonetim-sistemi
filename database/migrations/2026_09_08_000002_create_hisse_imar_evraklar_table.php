<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hisse satışı için imar müdürlüğü ile yazışmalar + imar durumu bilgileri.
     * Referans projedeki tasinmaz_imar_evraks + tasinmaz_imar_plan_notlaris'in
     * hisse-grubuna bağlı birleşik hali.
     */
    public function up(): void
    {
        Schema::create('hisse_imar_evraklar', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grup_no')->index();
            $table->foreignId('tasinmaz_id')->constrained('tasinmazlar')->cascadeOnDelete();

            // Giden evrak
            $table->string('imar_giden_yazi', 100)->nullable();
            $table->date('imar_giden_tarih')->nullable();
            $table->string('imar_giden_evrak', 500)->nullable();

            // Gelen evrak
            $table->string('imar_gelen_yazi', 100)->nullable();
            $table->date('imar_gelen_tarih')->nullable();
            $table->string('imar_gelen_evrak', 500)->nullable();

            // İmar bilgileri (opsiyonel — gelen imar durumundan)
            $table->string('imar_durum', 150)->nullable(); // Konut / Ticaret / Yeşil Alan vb.
            $table->string('emsal', 30)->nullable();
            $table->string('yencok', 30)->nullable();
            $table->text('plan_notlari')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hisse_imar_evraklar');
    }
};
