<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resimler', function (Blueprint $table) {
            $table->id();
            $table->morphs('sahip');
            $table->string('dosya_yolu', 500);
            $table->string('kucuk_yolu', 500)->nullable();
            $table->smallInteger('sira')->default(0);
            $table->boolean('kapak_mi')->default(false);
            $table->string('aciklama', 255)->nullable();
            $table->string('mime_type', 50);
            $table->unsignedBigInteger('boyut');
            $table->timestamps();

            $table->index(['sahip_type', 'sahip_id', 'sira'], 'resimler_sahip_sira_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resimler');
    }
};
