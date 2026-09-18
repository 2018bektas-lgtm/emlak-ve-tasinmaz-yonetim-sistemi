<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hisse satışı süreci içinde ilgili şubelerden gelen görüş yazışmaları.
     * Referans projedeki tasinmaz_goruses tablosunun hisse-grubuna bağlı hali.
     */
    public function up(): void
    {
        Schema::create('hisse_goruslar', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grup_no')->index(); // hisse başvuru grubu
            $table->foreignId('tasinmaz_id')->constrained('tasinmazlar')->cascadeOnDelete();
            $table->string('gorus_sube', 200);
            $table->date('giden_tarih')->nullable();
            $table->string('giden_yazi', 100)->nullable();
            $table->string('giden_evrak', 500)->nullable();
            $table->date('gelen_tarih')->nullable();
            $table->string('gelen_yazi', 100)->nullable();
            $table->string('gelen_evrak', 500)->nullable();
            $table->string('engel_var_yok', 30)->nullable(); // "Var" / "Yok"
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hisse_goruslar');
    }
};
