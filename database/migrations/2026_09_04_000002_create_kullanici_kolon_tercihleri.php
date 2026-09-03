<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kullanici_kolon_tercihleri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kullanici_id')->constrained('kullanicilar')->cascadeOnDelete();
            $table->string('tablo_anahtari', 40);
            $table->json('kolonlar');
            $table->timestamps();
            $table->unique(['kullanici_id', 'tablo_anahtari']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kullanici_kolon_tercihleri');
    }
};
