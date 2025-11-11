<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificação de Email - Salões</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f3f4f6;
            line-height: 1.6;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        .email-header {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            padding: 40px 20px;
            text-align: center;
        }
        .email-header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 32px;
            font-weight: bold;
            letter-spacing: -0.5px;
        }
        .email-body {
            padding: 40px 30px;
            color: #374151;
        }
        .greeting {
            font-size: 18px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 20px;
        }
        .message {
            font-size: 16px;
            color: #4b5563;
            margin-bottom: 30px;
        }
        .button-container {
            text-align: center;
            margin: 40px 0;
        }
        .button {
            display: inline-block;
            padding: 14px 32px;
            background-color: #3b82f6;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        .button:hover {
            background-color: #2563eb;
        }
        .info-box {
            background-color: #f0f9ff;
            border-left: 4px solid #3b82f6;
            padding: 16px 20px;
            margin: 30px 0;
            border-radius: 4px;
        }
        .info-box p {
            margin: 8px 0;
            font-size: 14px;
            color: #1e40af;
        }
        .footer {
            background-color: #f9fafb;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }
        .footer p {
            margin: 8px 0;
            font-size: 14px;
            color: #6b7280;
        }
        .footer .signature {
            color: #111827;
            font-weight: 600;
            margin-top: 20px;
        }
        .troubleshoot {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #e5e7eb;
        }
        .troubleshoot p {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 10px;
        }
        .troubleshoot a {
            color: #3b82f6;
            word-break: break-all;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <h1>Salões</h1>
        </div>

        <!-- Body -->
        <div class="email-body">
            <div class="greeting">Olá, {{ $userName }}!</div>

            <div class="message">
                Obrigado por se cadastrar no Salões! Para completar seu cadastro e começar a usar nossa plataforma, precisamos verificar seu endereço de email.
            </div>

            <div class="button-container">
                <a href="{{ $verificationUrl }}" class="button">Verificar Email</a>
            </div>

            <div class="info-box">
                <p><strong>✨ Após verificar seu email, você terá acesso completo à plataforma!</strong></p>
                <p>Se você não criou uma conta, pode ignorar este email com segurança.</p>
            </div>

            <div class="troubleshoot">
                <p><strong>Problemas ao clicar no botão?</strong></p>
                <p>Copie e cole o link abaixo no seu navegador:</p>
                <a href="{{ $verificationUrl }}">{{ $verificationUrl }}</a>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p class="signature">Atenciosamente,<br>Equipe Salões</p>
            <p>© {{ date('Y') }} Salões. Todos os direitos reservados.</p>
            <p style="font-size: 12px; color: #9ca3af; margin-top: 20px;">
                Este é um email automático, por favor não responda.
            </p>
        </div>
    </div>
</body>
</html>

