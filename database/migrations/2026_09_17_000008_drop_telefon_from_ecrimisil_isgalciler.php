<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecrimisil_isgalciler', function (Blueprint $table) {
            if (Schema::hasColumn('ecrimisil_isgalciler', 'telefon')) {
                $table->dropColumn('telefon');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ecrimisil_isgalciler', function (Blueprint $table) {
            $table->string('telefon', 30)->nullable()->after('tc_vergi_no');
        });
    }
};
