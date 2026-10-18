<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seçim listeleri: imar durumları, muhasebe kayıtları ve kayıt türleri
 * (ikisi hiyerarşik, parent_id ile) ve mevcut kullanım şekilleri.
 * Verileri seeder'lardan gelir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imar_durumlari', function (Blueprint $table) {
            $table->id();
            $table->string('ad', 100)->unique();
            $table->string('kod', 20)->nullable()->unique();
            $table->text('aciklama')->nullable();
            $table->smallInteger('sira')->default(0);
            $table->boolean('aktif_mi')->default(true);
            $table->timestamps();
            $table->index(['aktif_mi', 'sira']);
        });

        foreach (['muhasebe_kayitlari', 'kayit_turleri'] as $tablo) {
            Schema::create($tablo, function (Blueprint $table) use ($tablo) {
                $table->id();
                $table->foreignId('parent_id')->nullable()->constrained($tablo)->cascadeOnDelete();
                $table->string('ad', 100);
                $table->string('kod', 30)->nullable()->unique();
                $table->text('aciklama')->nullable();
                $table->smallInteger('sira')->default(0);
                $table->boolean('aktif_mi')->default(true);
                $table->timestamps();
                $table->index(['aktif_mi', 'sira']);
                $table->index(['parent_id', 'sira']);
            });
        }

        Schema::create('mevcut_kullanim_sekilleri', function (Blueprint $table) {
            $table->id();
            $table->string('ad', 150)->unique();
            $table->boolean('aktif_mi')->default(true)->index();
            $table->unsignedSmallInteger('sira')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mevcut_kullanim_sekilleri');
        Schema::dropIfExists('kayit_turleri');
        Schema::dropIfExists('muhasebe_kayitlari');
        Schema::dropIfExists('imar_durumlari');
    }
};
