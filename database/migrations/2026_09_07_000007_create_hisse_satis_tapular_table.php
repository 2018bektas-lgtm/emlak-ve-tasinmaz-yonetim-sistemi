<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hisse satışı tamamlandığında yeni malikin tapu tescil kaydı.
     * Ödedi=1 olan her başvuruya bir tescil satırı açılır (yevmiye no + PDF).
     */
    public function up(): void
    {
        Schema::create('hisse_satis_tapular', function (Blueprint $table) {
            $table->id();
            $table->foreignId('basvuru_id')->constrained('hisse_basvurulari')->cascadeOnDelete();
            $table->unsignedBigInteger('grup_no')->index();
            $table->date('tescil_tarihi');
            $table->string('yevmiye_no', 100)->nullable();
            $table->decimal('tescil_edilen_yuzolcum', 15, 2)->nullable(); // fiilen tescil edilen m²
            $table->string('tescil_evrak', 500)->nullable(); // yeni tapu senedi PDF
            $table->text('aciklama')->nullable();
            $table->timestamps();

            $table->unique('basvuru_id'); // her başvuru için tek tapu tescil
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hisse_satis_tapular');
    }
};
