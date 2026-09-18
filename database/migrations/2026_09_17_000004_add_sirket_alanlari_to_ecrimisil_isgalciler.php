<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecrimisil_isgalciler', function (Blueprint $table) {
            $table->string('cadde_sokak', 200)->nullable()->after('adres');
            $table->string('nitelik', 150)->nullable()->after('cadde_sokak')
                ->comment('Bakkal, büfe, satış ofisi vb. — işgalin niteliği');
        });
    }

    public function down(): void
    {
        Schema::table('ecrimisil_isgalciler', function (Blueprint $table) {
            $table->dropColumn(['cadde_sokak', 'nitelik']);
        });
    }
};
