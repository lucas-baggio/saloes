<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Plan;
use Exception;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\MercadoPagoConfig;

class MercadoPagoService
{
    private PaymentClient $paymentClient;

    public function __construct()
    {
        $accessToken = config('services.mercadopago.access_token');

        if (!$accessToken) {
            throw new Exception('Mercado Pago access token não configurado');
        }

        MercadoPagoConfig::setAccessToken($accessToken);
        MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::LOCAL);

        $this->paymentClient = new PaymentClient();
    }

    /**
     * Cria um pagamento via PIX
     */
    public function createPixPayment(Payment $payment, Plan $plan): array
    {
        try {
            // Log dos dados que serão enviados
            \Log::info('Criando pagamento PIX no Mercado Pago', [
                'payment_id' => $payment->id,
                'plan_id' => $plan->id,
                'amount' => $plan->price,
                'user_email' => $payment->user->email,
            ]);

            $paymentData = [
                'transaction_amount' => (float) $plan->price,
                'description' => "Plano {$plan->name}",
                'payment_method_id' => 'pix',
                'payer' => [
                    'email' => $payment->user->email,
                    'first_name' => explode(' ', $payment->user->name)[0] ?? $payment->user->name,
                    'last_name' => explode(' ', $payment->user->name)[1] ?? '',
                ],
                'metadata' => [
                    'payment_id' => $payment->id,
                    'plan_id' => $plan->id,
                    'user_id' => $payment->user_id,
                ],
            ];

            $mpPayment = $this->paymentClient->create($paymentData);

            // Log completo da resposta para debug
            \Log::info('Mercado Pago PIX Response', [
                'payment_id' => $mpPayment->id,
                'status' => $mpPayment->status,
                'status_detail' => $mpPayment->status_detail ?? null,
                'point_of_interaction' => isset($mpPayment->point_of_interaction) ? json_decode(json_encode($mpPayment->point_of_interaction), true) : null,
                'full_response' => json_decode(json_encode($mpPayment), true),
            ]);

            // Acessar os dados do PIX de forma segura
            $qrCode = null;
            $qrCodeBase64 = null;
            $ticketUrl = null;

            // Converte para array para facilitar o acesso
            $paymentArray = json_decode(json_encode($mpPayment), true);

            // Tenta acessar point_of_interaction->transaction_data
            if (isset($mpPayment->point_of_interaction)) {
                $poi = $mpPayment->point_of_interaction;

                if (isset($poi->transaction_data)) {
                    $transactionData = $poi->transaction_data;
                    $qrCode = $transactionData->qr_code ?? null;
                    $qrCodeBase64 = $transactionData->qr_code_base64 ?? null;
                }
            }

            // Se ainda não encontrou, tenta acessar como array
            if (!$qrCode && isset($paymentArray['point_of_interaction']['transaction_data'])) {
                $qrCode = $paymentArray['point_of_interaction']['transaction_data']['qr_code'] ?? null;
                $qrCodeBase64 = $paymentArray['point_of_interaction']['transaction_data']['qr_code_base64'] ?? null;
            }

            // Busca a URL do ticket para testar o pagamento
            // O ticket_url pode estar em diferentes locais na resposta
            if (isset($paymentArray['point_of_interaction']['transaction_data']['ticket_url'])) {
                $ticketUrl = $paymentArray['point_of_interaction']['transaction_data']['ticket_url'];
            } elseif (isset($mpPayment->point_of_interaction->transaction_data->ticket_url)) {
                $ticketUrl = $mpPayment->point_of_interaction->transaction_data->ticket_url;
            } elseif (isset($paymentArray['ticket_url'])) {
                $ticketUrl = $paymentArray['ticket_url'];
            }

            \Log::info('Dados PIX extraídos', [
                'qr_code' => $qrCode ? 'presente (' . strlen($qrCode) . ' chars)' : 'ausente',
                'qr_code_base64' => $qrCodeBase64 ? 'presente (' . strlen($qrCodeBase64) . ' chars)' : 'ausente',
                'qr_code_preview' => $qrCode ? substr($qrCode, 0, 50) . '...' : null,
                'ticket_url' => $ticketUrl ? 'presente' : 'ausente',
            ]);

            return [
                'success' => true,
                'payment_id' => $mpPayment->id,
                'status' => $this->mapStatus($mpPayment->status),
                'qr_code' => $qrCode,
                'qr_code_base64' => $qrCodeBase64,
                'ticket_url' => $ticketUrl,
            ];
        } catch (Exception $e) {
            // Tenta capturar mais detalhes do erro do Mercado Pago
            $errorDetails = [
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ];

            // Se o erro tem propriedades adicionais, tenta acessá-las
            if (method_exists($e, 'getResponse')) {
                try {
                    $response = $e->getResponse();
                    if ($response) {
                        $errorDetails['response'] = is_string($response) ? $response : json_encode($response);
                    }
                } catch (\Exception $ex) {
                    // Ignora se não conseguir acessar
                }
            }

            // Tenta acessar propriedades via reflection
            try {
                $reflection = new \ReflectionClass($e);
                foreach ($reflection->getProperties() as $property) {
                    $property->setAccessible(true);
                    $value = $property->getValue($e);
                    if ($value !== null) {
                        $errorDetails['property_' . $property->getName()] = is_string($value) || is_numeric($value) ? $value : json_encode($value);
                    }
                }
            } catch (\Exception $ex) {
                // Ignora se não conseguir acessar
            }

            // Log completo do erro
            \Log::error('Erro ao criar pagamento PIX', array_merge($errorDetails, [
                'trace' => $e->getTraceAsString(),
            ]));

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Cria um pagamento via Boleto
     */
    public function createBoletoPayment(Payment $payment, Plan $plan): array
    {
        try {
            $mpPayment = $this->paymentClient->create([
                'transaction_amount' => (float) $plan->price,
                'description' => "Plano {$plan->name}",
                'payment_method_id' => 'bolbradesco', // ou outro banco
                'payer' => [
                    'email' => $payment->user->email,
                    'first_name' => explode(' ', $payment->user->name)[0] ?? $payment->user->name,
                    'last_name' => explode(' ', $payment->user->name)[1] ?? '',
                ],
                'metadata' => [
                    'payment_id' => $payment->id,
                    'plan_id' => $plan->id,
                    'user_id' => $payment->user_id,
                ],
            ]);

            $transactionData = $mpPayment->point_of_interaction->transaction_data ?? null;

            return [
                'success' => true,
                'payment_id' => $mpPayment->id,
                'status' => $this->mapStatus($mpPayment->status),
                'barcode' => $transactionData->barcode ?? null,
                'barcode_base64' => $transactionData->barcode_base64 ?? null,
                'due_date' => $transactionData->expiration_date ?? null,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Cria um pagamento via Cartão de Crédito
     * Nota: O token do cartão deve ser criado no frontend usando o SDK do Mercado Pago
     */
    public function createCreditCardPayment(Payment $payment, Plan $plan, array $cardData, int $installments = 1): array
    {
        try {
            // O token deve vir do frontend (criado pelo SDK do Mercado Pago)
            if (!isset($cardData['token'])) {
                return [
                    'success' => false,
                    'error' => 'Token do cartão não fornecido. Use o SDK do Mercado Pago no frontend para criar o token.',
                ];
            }

            $mpPayment = $this->paymentClient->create([
                'transaction_amount' => (float) $plan->price,
                'description' => "Plano {$plan->name}",
                'payment_method_id' => $this->detectCardType($cardData['number']),
                'installments' => $installments,
                'payer' => [
                    'email' => $payment->user->email,
                    'identification' => [
                        'type' => 'CPF',
                        'number' => $cardData['cpf'] ?? '00000000000',
                    ],
                ],
                'token' => $cardData['token'],
                'metadata' => [
                    'payment_id' => $payment->id,
                    'plan_id' => $plan->id,
                    'user_id' => $payment->user_id,
                ],
            ]);

            return [
                'success' => true,
                'payment_id' => $mpPayment->id,
                'status' => $this->mapStatus($mpPayment->status),
                'transaction_id' => $mpPayment->id,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Busca o status de um pagamento
     */
    public function getPaymentStatus(string $paymentId): ?array
    {
        try {
            $mpPayment = $this->paymentClient->get($paymentId);

            return [
                'id' => $mpPayment->id,
                'status' => $this->mapStatus($mpPayment->status),
                'status_detail' => $mpPayment->status_detail,
            ];
        } catch (Exception $e) {
            \Log::error('Erro ao buscar status do pagamento', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Busca os dados completos de um pagamento (incluindo QR Code do PIX)
     */
    public function getFullPaymentData(string $paymentId): ?array
    {
        try {
            $mpPayment = $this->paymentClient->get($paymentId);

            $qrCode = null;
            $qrCodeBase64 = null;

            // Tenta extrair dados do PIX
            if (isset($mpPayment->point_of_interaction)) {
                $poi = $mpPayment->point_of_interaction;

                if (isset($poi->transaction_data)) {
                    $transactionData = $poi->transaction_data;
                    $qrCode = $transactionData->qr_code ?? null;
                    $qrCodeBase64 = $transactionData->qr_code_base64 ?? null;
                }
            }

            // Se ainda não encontrou, tenta como array
            if (!$qrCode) {
                $paymentArray = json_decode(json_encode($mpPayment), true);

                if (isset($paymentArray['point_of_interaction']['transaction_data']['qr_code'])) {
                    $qrCode = $paymentArray['point_of_interaction']['transaction_data']['qr_code'];
                }

                if (isset($paymentArray['point_of_interaction']['transaction_data']['qr_code_base64'])) {
                    $qrCodeBase64 = $paymentArray['point_of_interaction']['transaction_data']['qr_code_base64'];
                }
            }

            return [
                'qr_code' => $qrCode,
                'qr_code_base64' => $qrCodeBase64,
            ];
        } catch (Exception $e) {
            \Log::error('Erro ao buscar dados completos do pagamento', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }


    /**
     * Detecta o tipo de cartão pelo número
     */
    private function detectCardType(string $cardNumber): string
    {
        $cardNumber = preg_replace('/\D/', '', $cardNumber);
        $firstDigit = substr($cardNumber, 0, 1);

        return match ($firstDigit) {
            '4' => 'visa',
            '5' => 'mastercard',
            '3' => 'amex',
            default => 'visa',
        };
    }

    /**
     * Mapeia o status do Mercado Pago para o status interno
     */
    private function mapStatus(string $mpStatus): string
    {
        return match ($mpStatus) {
            'pending' => 'pending',
            'in_process', 'in_mediation' => 'processing',
            'approved' => 'approved',
            'rejected', 'cancelled', 'refunded', 'charged_back' => 'rejected',
            default => 'pending',
        };
    }
}


