<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mahalleler', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tkgm_id')->unique();
            $table->foreignId('ilce_id')->constrained('ilceler')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('ad', 150);
            $table->timestamps();

            $table->index(['ilce_id', 'ad']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mahalleler');
    }
};
