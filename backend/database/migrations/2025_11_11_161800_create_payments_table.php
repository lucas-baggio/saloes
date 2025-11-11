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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('plan_id')->constrained('plans')->onDelete('cascade');
            $table->foreignId('user_plan_id')->nullable()->constrained('user_plans')->onDelete('set null');
            $table->enum('payment_method', ['pix', 'boleto', 'credit_card']);
            $table->enum('status', ['pending', 'processing', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->decimal('amount', 10, 2);
            $table->string('mercadopago_payment_id')->nullable()->unique();
            $table->string('mercadopago_preference_id')->nullable();
            $table->text('qr_code')->nullable(); // Código PIX
            $table->text('qr_code_base64')->nullable(); // QR Code em base64
            $table->text('barcode')->nullable(); // Código de barras do boleto
            $table->text('barcode_base64')->nullable(); // Boleto em base64
            $table->date('due_date')->nullable(); // Data de vencimento do boleto
            $table->string('payment_url')->nullable(); // URL para pagamento
            $table->string('transaction_id')->nullable();
            $table->json('metadata')->nullable(); // Dados adicionais (cartão, parcelas, etc)
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

