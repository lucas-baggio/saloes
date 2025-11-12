<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('schedulings', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('establishment_id')->constrained('clients')->onDelete('set null');
            $table->index('client_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedulings', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropIndex(['client_id']);
            $table->dropColumn('client_id');
        });
    }
};

