<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bem-vindo ao Salões</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f7f6;
            margin: 0;
            padding: 0;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        .header {
            background-color: #3b82f6;
            color: #ffffff;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: bold;
        }
        .content {
            padding: 30px;
            color: #333333;
            line-height: 1.6;
        }
        .content h2 {
            color: #1a202c;
            font-size: 22px;
            margin-top: 0;
            margin-bottom: 15px;
        }
        .content p {
            margin-bottom: 15px;
        }
        .features {
            background-color: #f0f4f8;
            border-radius: 5px;
            padding: 20px;
            margin: 20px 0;
        }
        .features ul {
            margin: 0;
            padding-left: 20px;
        }
        .features li {
            margin-bottom: 10px;
        }
        .button-container {
            text-align: center;
            margin-top: 25px;
            margin-bottom: 25px;
        }
        .button {
            background-color: #3b82f6;
            color: #ffffff;
            padding: 12px 25px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            display: inline-block;
        }
        .footer {
            background-color: #f0f4f8;
            color: #666666;
            text-align: center;
            padding: 20px;
            font-size: 12px;
            border-top: 1px solid #e2e8f0;
        }
        .footer p {
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Salões</h1>
        </div>
        <div class="content">
            <h2>Bem-vindo, {{ $userName }}!</h2>
            <p>Ficamos muito felizes em tê-lo conosco! Sua conta foi criada com sucesso.</p>

            <div class="features">
                <p><strong>Com o Salões, você pode:</strong></p>
                <ul>
                    <li>Gerenciar seus estabelecimentos</li>
                    <li>Criar e organizar serviços</li>
                    <li>Agendar atendimentos</li>
                    <li>Acompanhar funcionários</li>
                    <li>Visualizar relatórios e estatísticas</li>
                </ul>
            </div>

            <p>Estamos aqui para ajudar você a gerenciar seu negócio de forma mais eficiente e organizada.</p>

            <div class="button-container">
                <a href="{{ env('FRONTEND_URL', 'http://localhost:4200') }}/dashboard" class="button">
                    Acessar Dashboard
                </a>
            </div>

            <p>Se você tiver alguma dúvida, não hesite em entrar em contato conosco.</p>
            <p>Obrigado,<br>A equipe Salões</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} Salões. Todos os direitos reservados.</p>
        </div>
    </div>
</body>
</html>

