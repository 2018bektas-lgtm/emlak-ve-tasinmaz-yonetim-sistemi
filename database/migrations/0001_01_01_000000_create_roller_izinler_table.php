<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Yetki sistemi: roller, izinler ve rol–izin eşlemesi.
 * Kullanıcılar tablosu rol_id ile buraya bağlandığı için önce oluşturulur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roller', function (Blueprint $table) {
            $table->id();
            $table->string('kod', 40)->unique();
            $table->string('ad', 100);
            $table->text('aciklama')->nullable();
            $table->boolean('sistem_mi')->default(false);
            $table->unsignedInteger('sira')->default(0);
            $table->timestamps();
        });

        Schema::create('izinler', function (Blueprint $table) {
            $table->id();
            $table->string('kod', 80)->unique();
            $table->string('grup', 40)->index();
            $table->string('ad', 150);
            $table->text('aciklama')->nullable();
            $table->timestamps();
        });

        Schema::create('rol_izin', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rol_id')->constrained('roller')->cascadeOnDelete();
            $table->foreignId('izin_id')->constrained('izinler')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['rol_id', 'izin_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rol_izin');
        Schema::dropIfExists('izinler');
        Schema::dropIfExists('roller');
    }
};
