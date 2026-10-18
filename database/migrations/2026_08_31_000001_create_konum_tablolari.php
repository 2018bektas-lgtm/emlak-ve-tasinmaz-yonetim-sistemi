<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * İl / ilçe / mahalle — TKGM kimlikleriyle. Veri `php artisan tkgm:senkronize`
 * komutuyla TKGM'den çekilir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iller', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tkgm_id')->unique();
            $table->string('ad', 100)->index();
            $table->timestamps();
        });

        Schema::create('ilceler', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tkgm_id')->unique();
            $table->foreignId('il_id')->constrained('iller')->cascadeOnUpdate();
            $table->string('ad', 100);
            $table->timestamps();
            $table->index(['il_id', 'ad']);
        });

        Schema::create('mahalleler', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tkgm_id')->unique();
            $table->foreignId('ilce_id')->constrained('ilceler')->cascadeOnUpdate();
            $table->string('ad', 150);
            $table->timestamps();
            $table->index(['ilce_id', 'ad']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mahalleler');
        Schema::dropIfExists('ilceler');
        Schema::dropIfExists('iller');
    }
};
