<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ilceler', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tkgm_id')->unique();
            $table->foreignId('il_id')->constrained('iller')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('ad', 100);
            $table->timestamps();

            $table->index(['il_id', 'ad']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ilceler');
    }
};
