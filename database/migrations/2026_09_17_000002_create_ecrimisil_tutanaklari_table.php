<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecrimisil_tutanaklari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isgalci_id')->constrained('ecrimisil_isgalciler')->cascadeOnDelete();

            $table->string('seri_no', 100)->nullable();
            $table->date('tutanak_tarihi')->nullable();
            $table->date('isgal_baslangic_tarihi')->nullable();
            $table->date('isgal_bitis_tarihi')->nullable()->comment('Boşsa devam ediyor');
            $table->text('aciklama')->nullable();

            $table->timestamps();

            $table->index('isgalci_id');
            $table->index('seri_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecrimisil_tutanaklari');
    }
};
