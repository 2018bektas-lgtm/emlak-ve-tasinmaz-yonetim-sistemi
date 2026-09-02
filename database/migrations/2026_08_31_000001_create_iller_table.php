<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iller', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tkgm_id')->unique();
            $table->string('ad', 100);
            $table->timestamps();

            $table->index('ad');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iller');
    }
};
