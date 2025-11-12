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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establishment_id')->constrained('establishments')->onDelete('cascade');
            $table->string('description');
            $table->string('category'); // aluguel, salarios, materiais, servicos, outros
            $table->decimal('amount', 10, 2);
            $table->date('due_date'); // Data de vencimento
            $table->date('payment_date')->nullable(); // Data de pagamento
            $table->enum('payment_method', ['pix', 'cartao_credito', 'cartao_debito', 'dinheiro', 'transferencia', 'boleto', 'outro'])->default('pix');
            $table->enum('status', ['pending', 'paid', 'overdue'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('establishment_id');
            $table->index('category');
            $table->index('due_date');
            $table->index('payment_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};

