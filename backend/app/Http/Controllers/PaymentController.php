<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\UserPlan;
use App\Services\MercadoPagoService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    private ?MercadoPagoService $mercadoPagoService = null;

    public function __construct()
    {
        // Injeção de dependência lazy para evitar erros na inicialização
        try {
            $this->mercadoPagoService = app(MercadoPagoService::class);
        } catch (\Exception $e) {
            Log::error('Erro ao inicializar MercadoPagoService', [
                'error' => $e->getMessage(),
            ]);
            // Continua sem o serviço - métodos que precisam dele devem verificar
        }
    }

    /**
     * Processa um pagamento
     */
    public function process(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'payment_method' => ['required', 'in:pix,boleto,credit_card'],
            'credit_card' => ['required_if:payment_method,credit_card', 'array'],
            'credit_card.token' => ['required_if:payment_method,credit_card', 'string'], // Token criado pelo SDK do Mercado Pago no frontend
            'credit_card.number' => ['sometimes', 'string'], // Para referência/detecção de tipo
            'credit_card.name' => ['sometimes', 'string'],
            'credit_card.cpf' => ['required_if:payment_method,credit_card', 'string'], // CPF necessário para cartão
            'installments' => ['sometimes', 'integer', 'min:1', 'max:12'],
        ]);

        $plan = Plan::findOrFail($data['plan_id']);

        if (!$plan->is_active) {
            return response()->json([
                'message' => 'Este plano não está disponível.',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Verifica se o serviço está disponível
        if (!$this->mercadoPagoService) {
            return response()->json([
                'message' => 'Serviço de pagamento não está disponível. Verifique as configurações.',
                'error' => 'MercadoPagoService não inicializado',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        DB::beginTransaction();

        try {
            // Cria o registro de pagamento
            $payment = Payment::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'payment_method' => $data['payment_method'],
                'status' => 'pending',
                'amount' => $plan->price,
                'metadata' => $data['credit_card'] ?? null,
            ]);

            // Processa o pagamento conforme o método
            $result = match ($data['payment_method']) {
                'pix' => $this->mercadoPagoService->createPixPayment($payment, $plan),
                'boleto' => $this->mercadoPagoService->createBoletoPayment($payment, $plan),
                'credit_card' => $this->mercadoPagoService->createCreditCardPayment(
                    $payment,
                    $plan,
                    $data['credit_card'],
                    $data['installments'] ?? 1
                ),
            };

            if (!$result['success']) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Erro ao processar pagamento.',
                    'error' => $result['error'] ?? 'Erro desconhecido',
                ], Response::HTTP_BAD_REQUEST);
            }

            // Atualiza o pagamento com os dados do Mercado Pago
            $payment->update([
                'mercadopago_payment_id' => $result['payment_id'] ?? null,
                'status' => $result['status'] ?? 'pending',
                'qr_code' => $result['qr_code'] ?? null,
                'qr_code_base64' => $result['qr_code_base64'] ?? null,
                'barcode' => $result['barcode'] ?? null,
                'barcode_base64' => $result['barcode_base64'] ?? null,
                'due_date' => $result['due_date'] ?? null,
                'transaction_id' => $result['transaction_id'] ?? null,
                'payment_url' => $result['ticket_url'] ?? null, // Usa payment_url para armazenar ticket_url
            ]);

            // Se o pagamento foi aprovado, cria/atualiza o user_plan
            if ($result['status'] === 'approved') {
                $this->activatePlan($user, $plan);
            }

            DB::commit();

            $payment->refresh();
            $payment->load('plan');

            // Log para debug
            Log::info('Pagamento retornado ao frontend', [
                'payment_id' => $payment->id,
                'has_qr_code' => !empty($payment->qr_code),
                'has_qr_code_base64' => !empty($payment->qr_code_base64),
                'qr_code_length' => $payment->qr_code ? strlen($payment->qr_code) : 0,
                'qr_code_base64_length' => $payment->qr_code_base64 ? strlen($payment->qr_code_base64) : 0,
                'qr_code_preview' => $payment->qr_code ? substr($payment->qr_code, 0, 50) . '...' : null,
            ]);

            // Garantir que os dados estão sendo retornados
            $responseData = [
                'id' => $payment->id,
                'status' => $payment->status,
                'payment_method' => $payment->payment_method,
                'amount' => $payment->amount,
                'qr_code' => $payment->qr_code,
                'qr_code_base64' => $payment->qr_code_base64,
                'barcode' => $payment->barcode,
                'barcode_base64' => $payment->barcode_base64,
                'due_date' => $payment->due_date?->format('Y-m-d'),
                'transaction_id' => $payment->transaction_id,
                'mercadopago_payment_id' => $payment->mercadopago_payment_id,
                'ticket_url' => $payment->payment_url, // Retorna como ticket_url para o frontend
                'plan' => $payment->plan,
                'created_at' => $payment->created_at,
                'updated_at' => $payment->updated_at,
            ];

            return response()->json($responseData, Response::HTTP_CREATED);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao processar pagamento: ' . $e->getMessage());

            return response()->json([
                'message' => 'Erro ao processar pagamento.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Verifica o status de um pagamento
     */
    public function getStatus(Request $request, string $paymentId)
    {
        $user = $request->user();

        $payment = Payment::where('id', $paymentId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Verifica se o serviço está disponível
        if (!$this->mercadoPagoService) {
            return response()->json([
                'message' => 'Serviço de pagamento não está disponível.',
                'error' => 'MercadoPagoService não inicializado',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        // Se tem ID do Mercado Pago, busca o status atualizado e dados do PIX
        if ($payment->mercadopago_payment_id) {
            $status = $this->mercadoPagoService->getPaymentStatus($payment->mercadopago_payment_id);

            if ($status) {
                // Busca os dados completos do pagamento para atualizar QR Code se necessário
                $fullPayment = $this->mercadoPagoService->getFullPaymentData($payment->mercadopago_payment_id);

                if ($fullPayment) {
                    $payment->update([
                        'status' => $status['status'],
                        'qr_code' => $fullPayment['qr_code'] ?? $payment->qr_code,
                        'qr_code_base64' => $fullPayment['qr_code_base64'] ?? $payment->qr_code_base64,
                    ]);
                } else {
                    $payment->update([
                        'status' => $status['status'],
                    ]);
                }

                // Se foi aprovado e ainda não tem user_plan, cria
                if ($status['status'] === 'approved' && !$payment->user_plan_id) {
                    $this->activatePlan($user, $payment->plan);
                    $payment->refresh();
                }
            }
        }

        $payment->load('plan');

        return response()->json($payment);
    }

    /**
     * Webhook do Mercado Pago
     *
     * Nota: O webhook_secret é opcional. Se configurado, valida a origem do webhook.
     * Você pode configurá-lo depois no painel do Mercado Pago se quiser segurança extra.
     */
    public function webhook(Request $request)
    {
        try {
            $data = $request->all();

            // Log do webhook recebido
            Log::info('Webhook Mercado Pago recebido', [
                'action' => $data['action'] ?? null,
                'type' => $data['type'] ?? null,
                'data_id' => $data['data']['id'] ?? null,
                'live_mode' => $data['live_mode'] ?? null,
            ]);

            // Validação opcional do webhook_secret (se configurado)
            $webhookSecret = config('services.mercadopago.webhook_secret');
            if ($webhookSecret) {
                $xSignature = $request->header('x-signature');
                $xRequestId = $request->header('x-request-id');

                // Validação básica - você pode implementar validação mais robusta se necessário
                if (!$xSignature || !$xRequestId) {
                    Log::warning('Webhook sem assinatura válida', $data);
                    return response()->json(['status' => 'error', 'message' => 'Assinatura inválida'], 401);
                }
            }

            // Verifica se é um evento de pagamento
            if (!isset($data['type']) || $data['type'] !== 'payment') {
                Log::info('Webhook ignorado - não é um evento de pagamento', ['type' => $data['type'] ?? null]);
                return response()->json(['status' => 'ok', 'message' => 'Evento ignorado']);
            }

            // Verifica se tem o ID do pagamento
            if (!isset($data['data']['id'])) {
                Log::warning('Webhook sem ID de pagamento', $data);
                return response()->json(['status' => 'error', 'message' => 'ID de pagamento não encontrado'], 400);
            }

            $paymentId = $data['data']['id'];

            // Busca o pagamento no banco
            $payment = Payment::where('mercadopago_payment_id', $paymentId)->first();

            if (!$payment) {
                Log::warning('Pagamento não encontrado no banco', ['mercadopago_payment_id' => $paymentId]);
                return response()->json(['status' => 'ok', 'message' => 'Pagamento não encontrado']);
            }

            // Verifica se o serviço está disponível
            if (!$this->mercadoPagoService) {
                Log::error('MercadoPagoService não está disponível no webhook');
                return response()->json([
                    'status' => 'ok',
                    'message' => 'Serviço não disponível, mas webhook recebido'
                ], 200);
            }

            // Busca o status atualizado do Mercado Pago
            try {
                $status = $this->mercadoPagoService->getPaymentStatus($paymentId);

                if ($status && isset($status['status'])) {
                    $payment->update([
                        'status' => $status['status'],
                    ]);

                    Log::info('Status do pagamento atualizado', [
                        'payment_id' => $payment->id,
                        'mercadopago_payment_id' => $paymentId,
                        'status' => $status['status'],
                    ]);

                    // Se foi aprovado, ativa o plano
                    if ($status['status'] === 'approved' && !$payment->user_plan_id) {
                        try {
                            $payment->load(['user', 'plan']);
                            if ($payment->user && $payment->plan) {
                                $this->activatePlan($payment->user, $payment->plan);
                                Log::info('Plano ativado via webhook', [
                                    'user_id' => $payment->user->id,
                                    'plan_id' => $payment->plan->id,
                                ]);
                            }
                        } catch (\Exception $e) {
                            Log::error('Erro ao ativar plano via webhook', [
                                'error' => $e->getMessage(),
                                'payment_id' => $payment->id,
                            ]);
                        }
                    }
                } else {
                    Log::warning('Status do pagamento não retornado corretamente', [
                        'payment_id' => $payment->id,
                        'status_response' => $status,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Erro ao buscar status do pagamento no Mercado Pago', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'payment_id' => $payment->id,
                    'mercadopago_payment_id' => $paymentId,
                ]);
                // Retorna OK mesmo em caso de erro para não bloquear o webhook
                return response()->json(['status' => 'ok', 'message' => 'Erro ao processar, mas webhook recebido']);
            }

            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::error('Erro ao processar webhook do Mercado Pago', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            // Retorna 200 para evitar que o Mercado Pago continue tentando
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao processar webhook',
            ], 200);
        }
    }

    /**
     * Ativa o plano do usuário
     */
    private function activatePlan($user, Plan $plan): void
    {
        // Cancela plano anterior se existir
        $currentPlan = $user->currentPlan;
        if ($currentPlan) {
            $currentPlan->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);
        }

        // Calcula a data de término
        $startsAt = now();
        $endsAt = match ($plan->interval) {
            'monthly' => $startsAt->copy()->addMonth(),
            'yearly' => $startsAt->copy()->addYear(),
            default => null,
        };

        // Cria o novo plano
        $userPlan = UserPlan::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        // Atualiza o pagamento com o user_plan_id
        Payment::where('user_id', $user->id)
            ->where('plan_id', $plan->id)
            ->whereNull('user_plan_id')
            ->update(['user_plan_id' => $userPlan->id]);
    }
}

