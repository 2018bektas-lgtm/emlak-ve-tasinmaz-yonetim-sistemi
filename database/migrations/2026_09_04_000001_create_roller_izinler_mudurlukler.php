<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Yetki + Müdürlük sistemi:
 *   roller           — rol tanımları (admin, yonetici, kullanici, gorusturucu)
 *   izinler          — izin tanımları (tasinmaz.view, tasinmaz.duzenle, …)
 *   rol_izin (pivot) — rolün sahip olduğu izinler
 *   mudurlukler      — belediye müdürlükleri
 *
 * kullanicilar'a role_id + mudurluk_id kolonları eklenir.
 * tasinmazlar'a mudurluk_id kolonu eklenir (kayıt hangi müdürlüğe ait).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roller', function (Blueprint $table) {
            $table->id();
            $table->string('kod', 40)->unique(); // 'admin', 'yonetici', ...
            $table->string('ad', 100);
            $table->text('aciklama')->nullable();
            $table->boolean('sistem_mi')->default(false); // sistem rolleri silinemez
            $table->unsignedInteger('sira')->default(0);
            $table->timestamps();
        });

        Schema::create('izinler', function (Blueprint $table) {
            $table->id();
            $table->string('kod', 80)->unique(); // 'tasinmaz.view', 'tasinmaz.duzenle', ...
            $table->string('grup', 40); // 'tasinmaz', 'harita', 'admin', ...
            $table->string('ad', 150);
            $table->text('aciklama')->nullable();
            $table->timestamps();
            $table->index('grup');
        });

        Schema::create('rol_izin', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rol_id')->constrained('roller')->cascadeOnDelete();
            $table->foreignId('izin_id')->constrained('izinler')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['rol_id', 'izin_id']);
        });

        Schema::create('mudurlukler', function (Blueprint $table) {
            $table->id();
            $table->string('kod', 40)->unique();
            $table->string('ad', 150);
            $table->text('aciklama')->nullable();
            $table->boolean('aktif_mi')->default(true);
            $table->unsignedInteger('sira')->default(0);
            $table->timestamps();
        });

        Schema::table('kullanicilar', function (Blueprint $table) {
            $table->foreignId('rol_id')->nullable()->after('sifre')
                ->constrained('roller')->nullOnDelete();
            $table->foreignId('mudurluk_id')->nullable()->after('rol_id')
                ->constrained('mudurlukler')->nullOnDelete();
            $table->boolean('aktif_mi')->default(true)->after('mudurluk_id');
        });

        Schema::table('tasinmazlar', function (Blueprint $table) {
            $table->foreignId('mudurluk_id')->nullable()->after('mahalle_id')
                ->constrained('mudurlukler')->nullOnDelete();
            $table->index('mudurluk_id');
        });
    }

    public function down(): void
    {
        Schema::table('tasinmazlar', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mudurluk_id');
        });
        Schema::table('kullanicilar', function (Blueprint $table) {
            $table->dropColumn('aktif_mi');
            $table->dropConstrainedForeignId('mudurluk_id');
            $table->dropConstrainedForeignId('rol_id');
        });
        Schema::dropIfExists('mudurlukler');
        Schema::dropIfExists('rol_izin');
        Schema::dropIfExists('izinler');
        Schema::dropIfExists('roller');
    }
};
