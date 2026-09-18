<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lojmanlar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tasinmaz_id')->nullable()->constrained('tasinmazlar')->nullOnDelete();
            $table->foreignId('mudurluk_id')->nullable()->constrained('mudurlukler')->nullOnDelete();
            $table->string('ad', 200)->comment('Lojman adı / etiketi — örn. "Merkez Lojman 2 - Daire 3"');
            $table->string('blok', 50)->nullable();
            $table->string('daire_no', 50)->nullable();
            $table->unsignedTinyInteger('kat')->nullable();
            $table->unsignedTinyInteger('oda_sayisi')->nullable()->comment('Örn. 3 (3+1 için)');
            $table->decimal('alan_m2', 10, 2)->nullable();
            $table->string('tip', 40)->nullable()->comment('Memur / İşçi / Görev / Sıra / Temsil vb.');
            $table->string('durum', 20)->default('bos')->comment('bos | dolu | bakimda | kullanim-disi');
            $table->text('aciklama')->nullable();
            $table->timestamps();

            $table->index('tasinmaz_id');
            $table->index('mudurluk_id');
            $table->index('durum');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lojmanlar');
    }
};
