<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasinmazlar', function (Blueprint $table) {
            $table->id();

            $table->foreignId('il_id')->constrained('iller')->restrictOnDelete();
            $table->foreignId('ilce_id')->constrained('ilceler')->restrictOnDelete();
            $table->foreignId('mahalle_id')->constrained('mahalleler')->restrictOnDelete();

            $table->string('ada', 20);
            $table->string('parsel', 20);
            $table->decimal('alan', 15, 2)->comment('m2');
            $table->string('nitelik', 100);

            $table->timestamps();

            // En sik: mahalleye gore ada/parsel arama (parsel arama ekrani)
            $table->index(['mahalle_id', 'ada', 'parsel']);
            // Il/ilce bazli listeleme + filtreleme (yonetim panelleri)
            $table->index(['il_id', 'ilce_id']);
            // Nitelik bazli filtreleme
            $table->index('nitelik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasinmazlar');
    }
};
