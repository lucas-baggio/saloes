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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained('clients')->onDelete('set null');
            $table->foreignId('service_id')->nullable()->constrained('services')->onDelete('set null');
            $table->foreignId('scheduling_id')->nullable()->constrained('schedulings')->onDelete('set null');
            $table->foreignId('establishment_id')->constrained('establishments')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Funcionário que realizou
            $table->decimal('amount', 10, 2);
            $table->enum('payment_method', ['pix', 'cartao_credito', 'cartao_debito', 'dinheiro', 'outro'])->default('pix');
            $table->date('sale_date');
            $table->enum('status', ['pending', 'paid', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('client_id');
            $table->index('service_id');
            $table->index('scheduling_id');
            $table->index('establishment_id');
            $table->index('user_id');
            $table->index('sale_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
