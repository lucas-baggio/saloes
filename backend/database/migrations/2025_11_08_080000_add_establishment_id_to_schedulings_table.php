<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('schedulings', function (Blueprint $table) {
            $table->foreignId('establishment_id')->nullable()->after('id')->constrained('establishments')->onDelete('cascade');
        });

        // Preencher establishment_id baseado no service_id dos agendamentos existentes
        DB::statement('
            UPDATE schedulings
            SET establishment_id = (
                SELECT establishment_id
                FROM services
                WHERE services.id = schedulings.service_id
            )
        ');

        // Tornar establishment_id obrigatório após preencher
        Schema::table('schedulings', function (Blueprint $table) {
            $table->foreignId('establishment_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedulings', function (Blueprint $table) {
            $table->dropForeign(['establishment_id']);
            $table->dropColumn('establishment_id');
        });
    }
};

