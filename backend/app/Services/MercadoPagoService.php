<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Plan;
use Exception;

class MercadoPagoService
{
    private $paymentClient;
    private bool $isAvailable = false;

    public function __construct()
    {
        // Verifica se as classes do Mercado Pago estão disponíveis
        if (!class_exists(\MercadoPago\MercadoPagoConfig::class)) {
            \Log::error('SDK do Mercado Pago não está instalado. Execute: composer require mercadopago/dx-php');
            return;
        }

        $accessToken = config('services.mercadopago.access_token');

        if (!$accessToken) {
            \Log::error('Mercado Pago access token não configurado');
            return;
        }

        try {
            \MercadoPago\MercadoPagoConfig::setAccessToken($accessToken);
            \MercadoPago\MercadoPagoConfig::setRuntimeEnviroment(\MercadoPago\MercadoPagoConfig::LOCAL);

            $this->paymentClient = new \MercadoPago\Client\Payment\PaymentClient();
            $this->isAvailable = true;
        } catch (\Exception $e) {
            \Log::error('Erro ao inicializar MercadoPagoService', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Verifica se o serviço está disponível
     */
    private function checkAvailability(): void
    {
        if (!$this->isAvailable) {
            throw new Exception('Mercado Pago SDK não está disponível. Verifique se o pacote está instalado (composer require mercadopago/dx-php) e se o access_token está configurado.');
        }
    }

    /**
     * Cria um pagamento via PIX
     */
    public function createPixPayment(Payment $payment, Plan $plan): array
    {
        try {
            $this->checkAvailability();

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
            $this->checkAvailability();

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
            $this->checkAvailability();

            // O token deve vir do frontend (criado pelo SDK do Mercado Pago)
            if (!isset($cardData['token']) || empty($cardData['token'])) {
                \Log::error('Token do cartão não fornecido', [
                    'payment_id' => $payment->id,
                    'card_data_keys' => array_keys($cardData),
                ]);
                return [
                    'success' => false,
                    'error' => 'Token do cartão não fornecido. Use o SDK do Mercado Pago no frontend para criar o token.',
                ];
            }

            // Quando usamos token, o Mercado Pago ainda exige expiration_month e expiration_year
            // mesmo que eles estejam no token. Isso é uma limitação/bug da API do Mercado Pago.
            // Precisamos extrair esses dados do token ou solicitá-los novamente do frontend.

            // Valida e formata CPF
            $cpf = $cardData['cpf'] ?? '00000000000';
            $cpf = preg_replace('/\D/', '', $cpf);
            if (strlen($cpf) !== 11) {
                \Log::warning('CPF inválido ou não fornecido', [
                    'payment_id' => $payment->id,
                    'cpf_length' => strlen($cpf),
                ]);
                // Para testes no sandbox, podemos usar um CPF padrão
                $cpf = '00000000000';
            }

            // IMPORTANTE: Conforme a documentação oficial do Mercado Pago Brasil,
            // quando usamos token, os dados de expiração (expiration_month/expiration_year)
            // JÁ ESTÃO ENCAPSULADOS NO TOKEN gerado no frontend.
            // Portanto, NÃO devemos enviar esses campos no payload do pagamento.
            // O token foi criado com card_expiration_month e card_expiration_year no frontend,
            // e esses dados estão contidos no token.
            //
            // Fonte: https://www.mercadopago.com.br/developers/pt/reference/payments/_payments/post
            //
            // Nota: Não precisamos mais validar ou formatar expiry_month/expiry_year aqui,
            // pois esses dados não serão enviados no payload do pagamento.

            // Prepara os dados do pagamento
            // FLUXO CORRETO DO MERCADO PAGO:
            // 1. Frontend: Cria token com expiration_month/expiration_year ✅ (já feito)
            // 2. Backend: Cria pagamento usando APENAS o token ✅ (fazendo aqui)
            //
            // IMPORTANTE: NÃO enviar expiration_month/expiration_year no payload do pagamento!
            // Esses dados já estão no token criado no frontend.
            $paymentRequest = [
                'transaction_amount' => (float) $plan->price,
                'description' => "Plano {$plan->name}",
                'installments' => $installments,
                'token' => $cardData['token'], // Token criado no frontend contém TODOS os dados do cartão
                'payer' => [
                    'email' => $payment->user->email,
                    'identification' => [
                        'type' => 'CPF',
                        'number' => $cpf,
                    ],
                ],
                'statement_descriptor' => 'SALOES',
                'metadata' => [
                    'payment_id' => (string) $payment->id,
                    'plan_id' => (string) $plan->id,
                    'user_id' => (string) $payment->user_id,
                ],
            ];
            // NÃO enviar: expiration_month, expiration_year, payment_method_id, capture
            // O token contém todos os dados necessários do cartão

            // Log da requisição completa (sem dados sensíveis)
            \Log::info('Requisição de pagamento preparada - usando apenas token (sem expiration_month/expiration_year)', [
                'payment_id' => $payment->id,
                'has_token' => !empty($cardData['token']),
                'token_length' => strlen($cardData['token'] ?? ''),
                'token_preview' => substr($cardData['token'] ?? '', 0, 8) . '...' . substr($cardData['token'] ?? '', -4),
                'payment_request_keys' => array_keys($paymentRequest),
                'has_expiration_month' => isset($paymentRequest['expiration_month']),
                'has_expiration_year' => isset($paymentRequest['expiration_year']),
                'has_payment_method_id' => isset($paymentRequest['payment_method_id']),
                'note' => 'Fluxo correto: token criado no frontend, backend usa APENAS o token',
            ]);

            // Valida que o token não está vazio
            if (empty($cardData['token']) || strlen($cardData['token']) < 10) {
                \Log::error('Token do cartão inválido ou muito curto', [
                    'payment_id' => $payment->id,
                    'token_length' => strlen($cardData['token'] ?? ''),
                ]);
                return [
                    'success' => false,
                    'error' => 'Token do cartão inválido. Por favor, tente novamente.',
                ];
            }

            \Log::info('Criando pagamento com cartão de crédito no Mercado Pago via API REST', [
                'payment_id' => $payment->id,
                'plan_id' => $plan->id,
                'amount' => $plan->price,
                'installments' => $installments,
                'has_token' => !empty($cardData['token']),
                'token_length' => strlen($cardData['token']),
                'token_preview' => substr($cardData['token'], 0, 8) . '...' . substr($cardData['token'], -4),
                'cpf' => substr($cpf, 0, 3) . '***' . substr($cpf, -2),
                'payment_request_keys' => array_keys($paymentRequest),
            ]);

            // Usa a API REST diretamente (mesma abordagem do frontend para criar token)
            // Isso garante consistência e evita problemas de validação do SDK
            try {
                $accessToken = config('services.mercadopago.access_token');
                $url = 'https://api.mercadopago.com/v1/payments';

                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . $accessToken,
                    ],
                    CURLOPT_POSTFIELDS => json_encode($paymentRequest),
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);

                if ($curlError) {
                    throw new Exception('Erro ao chamar API do Mercado Pago: ' . $curlError);
                }

                $responseData = json_decode($response, true);

                if ($httpCode !== 201 && $httpCode !== 200) {
                    \Log::error('Erro ao criar pagamento via API REST', [
                        'payment_id' => $payment->id,
                        'http_code' => $httpCode,
                        'response' => $responseData,
                    ]);

                    $errorMessage = $responseData['message'] ?? 'Erro ao processar pagamento';
                    if (isset($responseData['cause']) && is_array($responseData['cause'])) {
                        $errorMessage .= ': ' . ($responseData['cause'][0]['description'] ?? '');
                    }

                    return [
                        'success' => false,
                        'error' => $errorMessage,
                    ];
                }

                \Log::info('Pagamento criado com sucesso via API REST', [
                    'payment_id' => $payment->id,
                    'mp_payment_id' => $responseData['id'] ?? null,
                    'status' => $responseData['status'] ?? null,
                ]);

                return [
                    'success' => true,
                    'payment_id' => $responseData['id'],
                    'status' => $this->mapStatus($responseData['status'] ?? 'pending'),
                    'transaction_id' => $responseData['id'],
                ];

            } catch (\Exception $createException) {
                \Log::error('Exceção ao criar pagamento no Mercado Pago', [
                    'payment_id' => $payment->id,
                    'exception_class' => get_class($createException),
                    'exception_message' => $createException->getMessage(),
                    'exception_trace' => $createException->getTraceAsString(),
                ]);

                return [
                    'success' => false,
                    'error' => 'Erro ao processar pagamento: ' . $createException->getMessage(),
                ];
            }
        } catch (\MercadoPago\Exceptions\MPApiException $e) {
            // Captura erros específicos da API do Mercado Pago
            $errorDetails = [
                'payment_id' => $payment->id,
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'status_code' => $e->getStatusCode(),
                'card_data' => [
                    'has_token' => isset($cardData['token']),
                    'token_length' => isset($cardData['token']) ? strlen($cardData['token']) : 0,
                    'has_number' => isset($cardData['number']),
                    'has_cpf' => isset($cardData['cpf']),
                    'installments' => $installments,
                ],
            ];

            // Obtém a resposta da API
            try {
                $apiResponse = $e->getApiResponse();
                if ($apiResponse) {
                    $errorContent = $apiResponse->getContent();
                    $errorDetails['response_content'] = $errorContent;
                    $errorDetails['status_code'] = $apiResponse->getStatusCode();
                }
            } catch (\Exception $ex) {
                $errorDetails['response_error'] = $ex->getMessage();
            }

            \Log::error('Erro ao processar pagamento com cartão de crédito (MPApiException)', $errorDetails);

            // Tenta extrair mensagem de erro mais detalhada
            $errorMessage = $e->getMessage();
            if (isset($errorDetails['response_content']) && is_array($errorDetails['response_content'])) {
                $errorData = $errorDetails['response_content'];

                // Tenta extrair mensagem de erro
                if (isset($errorData['message'])) {
                    $errorMessage = $errorData['message'];
                } elseif (isset($errorData['error'])) {
                    $errorMessage = $errorData['error'];
                }

                // Tenta extrair causas do erro
                if (isset($errorData['cause']) && is_array($errorData['cause'])) {
                    $causes = [];
                    foreach ($errorData['cause'] as $cause) {
                        if (is_array($cause)) {
                            $description = $cause['description'] ?? $cause['code'] ?? '';
                            if ($description) {
                                $causes[] = $description;
                            }
                        } else {
                            $causes[] = (string) $cause;
                        }
                    }
                    if (!empty($causes)) {
                        $errorMessage .= ': ' . implode(', ', array_filter($causes));
                    }
                }
            }

            return [
                'success' => false,
                'error' => $errorMessage,
            ];
        } catch (\MercadoPago\Exceptions\MercadoPagoException $e) {
            // Captura outros tipos de exceções do Mercado Pago
            \Log::error('Erro ao processar pagamento com cartão de crédito (MercadoPagoException)', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'card_data' => [
                    'has_token' => isset($cardData['token']),
                    'token_length' => isset($cardData['token']) ? strlen($cardData['token']) : 0,
                    'has_number' => isset($cardData['number']),
                    'has_cpf' => isset($cardData['cpf']),
                    'installments' => $installments,
                ],
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        } catch (Exception $e) {
            // Captura outros tipos de exceções
            \Log::error('Erro ao processar pagamento com cartão de crédito (Exception)', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'card_data' => [
                    'has_token' => isset($cardData['token']),
                    'token_length' => isset($cardData['token']) ? strlen($cardData['token']) : 0,
                    'has_number' => isset($cardData['number']),
                    'has_cpf' => isset($cardData['cpf']),
                    'installments' => $installments,
                ],
            ]);

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
            $this->checkAvailability();

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


