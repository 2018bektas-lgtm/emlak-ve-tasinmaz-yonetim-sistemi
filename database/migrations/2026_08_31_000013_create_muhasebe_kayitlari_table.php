<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('muhasebe_kayitlari', function (Blueprint $table) {
            $table->id();
            $table->string('ad', 100)->unique();
            $table->string('kod', 30)->unique()->nullable();
            $table->text('aciklama')->nullable();
            $table->smallInteger('sira')->default(0);
            $table->boolean('aktif_mi')->default(true);
            $table->timestamps();

            $table->index(['aktif_mi', 'sira']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('muhasebe_kayitlari');
    }
};
