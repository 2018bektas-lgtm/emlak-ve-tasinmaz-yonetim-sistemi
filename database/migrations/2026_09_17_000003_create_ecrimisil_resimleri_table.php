<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecrimisil_resimleri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isgalci_id')->constrained('ecrimisil_isgalciler')->cascadeOnDelete();
            $table->foreignId('tutanak_id')->nullable()->constrained('ecrimisil_tutanaklari')->nullOnDelete();
            $table->string('dosya_yolu', 500);
            $table->string('aciklama', 300)->nullable();
            $table->timestamps();

            $table->index('isgalci_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecrimisil_resimleri');
    }
};
