<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $silinecek = [];
        if (Schema::hasColumn('bagimsiz_bolumler', 'arsa_payi_pay')) {
            $silinecek[] = 'arsa_payi_pay';
        }
        if (Schema::hasColumn('bagimsiz_bolumler', 'arsa_payi_payda')) {
            $silinecek[] = 'arsa_payi_payda';
        }
        if ($silinecek === []) {
            return;
        }

        Schema::table('bagimsiz_bolumler', function (Blueprint $table) use ($silinecek) {
            $table->dropColumn($silinecek);
        });
    }

    public function down(): void
    {
        Schema::table('bagimsiz_bolumler', function (Blueprint $table) {
            if (! Schema::hasColumn('bagimsiz_bolumler', 'arsa_payi_pay')) {
                $table->unsignedInteger('arsa_payi_pay')->nullable()->after('cephe');
            }
            if (! Schema::hasColumn('bagimsiz_bolumler', 'arsa_payi_payda')) {
                $table->unsignedInteger('arsa_payi_payda')->nullable()->after('arsa_payi_pay');
            }
        });
    }
};
