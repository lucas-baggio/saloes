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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('cpf')->nullable();
            $table->date('birth_date')->nullable();
            $table->text('address')->nullable();
            $table->text('anamnesis')->nullable(); // Anamnese/observações médicas
            $table->text('notes')->nullable(); // Observações gerais
            $table->string('photo')->nullable(); // URL da foto
            $table->json('allergies')->nullable(); // Alergias (array)
            $table->timestamps();

            $table->index('owner_id');
            $table->index('name');
            $table->index('phone');
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
