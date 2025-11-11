<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lembrete de Agendamento</title>
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
            background-color: #f59e0b;
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
        .scheduling-details {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            border-radius: 5px;
            padding: 20px;
            margin: 20px 0;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #fde68a;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: bold;
            color: #92400e;
        }
        .detail-value {
            color: #78350f;
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
            <h1>⏰ Lembrete de Agendamento</h1>
        </div>
        <div class="content">
            <h2>Olá, {{ $clientName }}!</h2>
            <p>Este é um lembrete de que você tem um agendamento em <strong>{{ $reminderText }}</strong>.</p>

            <div class="scheduling-details">
                <div class="detail-row">
                    <span class="detail-label">Data:</span>
                    <span class="detail-value">{{ $schedulingDate }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Horário:</span>
                    <span class="detail-value">{{ $schedulingTime }}</span>
                </div>
                @if($scheduling->service)
                <div class="detail-row">
                    <span class="detail-label">Serviço:</span>
                    <span class="detail-value">{{ $scheduling->service->name }}</span>
                </div>
                @endif
                @if($scheduling->establishment)
                <div class="detail-row">
                    <span class="detail-label">Estabelecimento:</span>
                    <span class="detail-value">{{ $scheduling->establishment->name }}</span>
                </div>
                @endif
            </div>

            <p>Não se esqueça de chegar alguns minutos antes do horário agendado.</p>
            <p>Se precisar alterar ou cancelar, entre em contato conosco o quanto antes.</p>

            <p>Obrigado,<br>A equipe Salões</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} Salões. Todos os direitos reservados.</p>
        </div>
    </div>
</body>
</html>

