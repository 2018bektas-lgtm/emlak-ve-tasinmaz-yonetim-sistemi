<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasinmaz_imarlar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tasinmaz_id')->unique()->constrained('tasinmazlar')->cascadeOnDelete();
            $table->foreignId('imar_durumu_id')->nullable()->constrained('imar_durumlari')->restrictOnDelete();
            $table->decimal('emsal', 5, 2)->nullable();
            $table->string('yenaz_yencok', 50)->nullable();
            $table->text('imar_notu')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasinmaz_imarlar');
    }
};
