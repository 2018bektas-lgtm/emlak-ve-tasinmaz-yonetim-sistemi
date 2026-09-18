<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lojman_evraklari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('basvuru_id')->nullable()->constrained('lojman_basvurulari')->cascadeOnDelete();
            $table->foreignId('lojman_id')->nullable()->constrained('lojmanlar')->cascadeOnDelete();
            $table->string('kategori', 30)->comment('basvuru | gorus-gelen | gorus-giden | encumen | tahsis | diger');
            $table->string('evrak_no', 100)->nullable();
            $table->date('evrak_tarihi')->nullable();
            $table->string('dosya_yolu', 500)->nullable();
            $table->text('aciklama')->nullable();
            $table->timestamps();

            $table->index('basvuru_id');
            $table->index('lojman_id');
            $table->index('kategori');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lojman_evraklari');
    }
};
