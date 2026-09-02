<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meclis_kararlari', function (Blueprint $table) {
            $table->id();
            $table->morphs('sahip');
            $table->string('karar_tipi', 30);
            $table->string('karar_no', 50);
            $table->date('karar_tarihi');
            $table->text('karar_ozeti')->nullable();
            $table->string('karar_pdf', 500)->nullable();
            $table->timestamps();

            $table->index(['sahip_type', 'sahip_id', 'karar_tarihi'], 'meclis_kararlari_sahip_tarih_index');
            $table->index('karar_tipi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meclis_kararlari');
    }
};
