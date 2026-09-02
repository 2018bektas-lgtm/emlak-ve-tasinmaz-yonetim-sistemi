<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tapular', function (Blueprint $table) {
            $table->id();
            $table->morphs('sahip');
            $table->string('takbis_zemin_no', 30);
            $table->string('cilt_no', 20);
            $table->string('sayfa_no', 20);
            $table->string('tapu_durumu', 20)->default('aktif');
            $table->string('tapu_kaydi_pdf', 500)->nullable();
            $table->date('tapu_tarihi')->nullable();
            $table->timestamps();

            $table->unique(['sahip_type', 'sahip_id'], 'tapular_sahip_unique');
            $table->index('takbis_zemin_no');
            $table->index('tapu_durumu');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tapular');
    }
};
