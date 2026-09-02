<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasinmaz_hisseler', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tasinmaz_id')->constrained('tasinmazlar')->cascadeOnDelete();
            $table->unsignedInteger('hisse_no')->nullable();
            $table->decimal('hisse_yuzolcum', 15, 2)->nullable();
            $table->string('edinme_sekli', 100)->nullable();
            $table->unsignedInteger('yevmiye_no')->nullable();
            $table->date('edinme_tarihi')->nullable();
            $table->string('kayitlardan_cikis', 150)->nullable();
            $table->date('kayitlardan_cikistarihi')->nullable();
            $table->enum('hisse_durum', ['aktif', 'pasif'])->default('aktif');
            $table->enum('islem_tipi', ['alis', 'satis'])->nullable();
            // Bedel bilgileri
            $table->decimal('maliyet_bedeli', 15, 2)->nullable();
            $table->decimal('rayic_bedel', 15, 2)->nullable();
            $table->decimal('emlak_vd', 15, 2)->nullable();
            $table->decimal('iz_bedeli', 15, 2)->nullable();
            $table->unsignedSmallInteger('sira')->default(0);
            $table->timestamps();

            $table->index(['tasinmaz_id', 'hisse_durum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasinmaz_hisseler');
    }
};
