# 🏢 Salões - Sistema de Gestão para Salões de Beleza

Sistema completo de gestão para salões de beleza, incluindo gerenciamento de estabelecimentos, serviços, agendamentos, funcionários e relatórios.

## 📋 Índice

- [Características](#-características)
- [Tecnologias](#-tecnologias)
- [Pré-requisitos](#-pré-requisitos)
- [Instalação](#-instalação)
- [Configuração](#-configuração)
- [Uso](#-uso)
- [Estrutura do Projeto](#-estrutura-do-projeto)
- [API](#-api)
- [Deploy](#-deploy)
- [Contribuindo](#-contribuindo)

## ✨ Características

### 🔐 Autenticação e Segurança

- ✅ Sistema de login/registro
- ✅ Recuperação de senha por email
- ✅ Verificação de email
- ✅ Controle de acesso por roles (Admin, Proprietário, Funcionário)
- ✅ Proteção de rotas com guards

### 🏪 Gestão de Estabelecimentos

- ✅ CRUD completo de estabelecimentos
- ✅ Isolamento de dados por proprietário
- ✅ Estatísticas por estabelecimento

### 💼 Gestão de Serviços

- ✅ CRUD completo de serviços
- ✅ Sub-serviços com cálculo automático de preço
- ✅ Atribuição de funcionários a serviços
- ✅ Preços e descrições detalhadas

### 📅 Agendamentos

- ✅ CRUD completo de agendamentos
- ✅ Calendário visual mensal
- ✅ Filtros avançados (data, serviço, estabelecimento, status)
- ✅ Busca por nome do cliente
- ✅ Paginação e ordenação
- ✅ Exportação para CSV
- ✅ Histórico de ações
- ✅ Validação de conflitos de horário
- ✅ Status: Pendente, Confirmado, Concluído, Cancelado

### 👥 Gestão de Funcionários

- ✅ CRUD completo de funcionários
- ✅ Associação a estabelecimentos
- ✅ Estatísticas de performance (receita, serviços, agendamentos)
- ✅ Dashboard específico para funcionários

### 📊 Dashboard e Relatórios

- ✅ Estatísticas gerais (estabelecimentos, serviços, agendamentos, receita)
- ✅ Gráficos de receita por período
- ✅ Top serviços por receita
- ✅ Agendamentos por status
- ✅ Comparação de períodos (crescimento)

### 📧 Notificações por Email

- ✅ Email de boas-vindas
- ✅ Confirmação de agendamento
- ✅ Lembretes de agendamento (24h e 1h antes)
- ✅ Notificações de mudança de status

## 🛠 Tecnologias

### Backend

- **Laravel 11** - Framework PHP
- **MySQL** - Banco de dados
- **Laravel Sanctum** - Autenticação API
- **Laravel Queue** - Sistema de filas para emails
- **Carbon** - Manipulação de datas

### Frontend

- **Angular 17** - Framework JavaScript
- **TypeScript** - Linguagem
- **Tailwind CSS** - Framework CSS
- **Chart.js** - Gráficos interativos
- **SweetAlert2** - Alertas personalizados
- **RxJS** - Programação reativa

## 📦 Pré-requisitos

Antes de começar, certifique-se de ter instalado:

- **PHP 8.2+** com extensões:
  - BCMath
  - Ctype
  - Fileinfo
  - JSON
  - Mbstring
  - OpenSSL
  - PDO
  - Tokenizer
  - XML
- **Composer** 2.0+
- **Node.js** 18+ e **npm** 9+
- **MySQL** 8.0+ ou **MariaDB** 10.3+
- **Git**

## 🚀 Instalação

### 1. Clone o repositório

```bash
git clone <url-do-repositorio>
cd Salões
```

### 2. Backend (Laravel)

```bash
cd backend

# Instalar dependências
composer install

# Copiar arquivo de ambiente
cp .env.example .env

# Gerar chave da aplicação
php artisan key:generate

# Configurar banco de dados no .env (veja seção Configuração)

# Executar migrations
php artisan migrate

# (Opcional) Popular banco com dados de teste
php artisan db:seed
```

### 3. Frontend (Angular)

```bash
cd frontend

# Instalar dependências
npm install

# (Opcional) Se houver problemas com peer dependencies
npm install --legacy-peer-deps
```

## ⚙️ Configuração

### Backend (.env)

Edite o arquivo `backend/.env` com suas configurações:

```env
# Aplicação
APP_NAME="Salões"
APP_ENV=local
APP_KEY=base64:... (gerado automaticamente)
APP_DEBUG=true
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:4200

# Banco de dados
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sl_db
DB_USERNAME=seu_usuario
DB_PASSWORD=sua_senha

# Email (para notificações)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=seu_email@gmail.com
MAIL_PASSWORD=sua_senha_app
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=seu_email@gmail.com
MAIL_FROM_NAME="${APP_NAME}"

# Queue (para processar emails)
QUEUE_CONNECTION=database
# Para desenvolvimento, use: QUEUE_CONNECTION=sync
```

### Frontend (environment.ts)

Edite `frontend/src/environments/environment.ts`:

```typescript
export const environment = {
  production: false,
  apiUrl: "http://localhost:8000/api",
};
```

## 🎯 Uso

### Desenvolvimento

#### Backend

```bash
cd backend

# Iniciar servidor de desenvolvimento
php artisan serve
# Servidor rodará em http://localhost:8000

# (Se usar filas) Processar jobs de email
php artisan queue:work

# (Opcional) Agendar tarefas (lembretes)
php artisan schedule:work
```

#### Frontend

```bash
cd frontend

# Iniciar servidor de desenvolvimento
ng serve
# ou
npm start
# Aplicação rodará em http://localhost:4200
```

### Comandos Úteis

#### Backend

```bash
# Criar nova migration
php artisan make:migration nome_da_migration

# Executar migrations
php artisan migrate

# Reverter última migration
php artisan migrate:rollback

# Criar controller
php artisan make:controller NomeController

# Criar model
php artisan make:model NomeModel

# Limpar cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Testar comando de lembretes
php artisan schedulings:send-reminders --type=24h
php artisan schedulings:send-reminders --type=1h
```

#### Frontend

```bash
# Gerar novo componente
ng generate component nome-do-componente

# Gerar novo serviço
ng generate service nome-do-servico

# Build para produção
ng build --configuration production

# Executar testes
ng test
```

## 📁 Estrutura do Projeto

```
Salões/
├── backend/                 # API Laravel
│   ├── app/
│   │   ├── Console/
│   │   │   └── Commands/    # Comandos artisan
│   │   ├── Http/
│   │   │   ├── Controllers/ # Controladores
│   │   │   └── Middleware/  # Middlewares
│   │   ├── Models/          # Models Eloquent
│   │   └── Notifications/   # Notificações por email
│   ├── database/
│   │   ├── migrations/      # Migrations
│   │   └── seeders/         # Seeders
│   ├── resources/
│   │   └── views/
│   │       └── emails/      # Templates de email
│   ├── routes/
│   │   ├── api.php          # Rotas da API
│   │   └── console.php      # Agendamento de tarefas
│   └── .env                 # Variáveis de ambiente
│
└── frontend/                # Aplicação Angular
    ├── src/
    │   ├── app/
    │   │   ├── components/  # Componentes
    │   │   ├── guards/      # Guards de rota
    │   │   ├── models/      # Interfaces TypeScript
    │   │   ├── services/    # Serviços
    │   │   └── app.routes.ts # Rotas
    │   └── environments/    # Configurações de ambiente
    └── angular.json          # Configuração Angular
```

## 🔌 API

### Autenticação

Todas as rotas (exceto login/registro) requerem autenticação via Bearer Token.

**Headers necessários:**

```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

### Endpoints Principais

#### Autenticação

- `POST /api/auth/register` - Registrar novo usuário
- `POST /api/auth/login` - Fazer login
- `POST /api/auth/logout` - Fazer logout
- `GET /api/auth/me` - Obter usuário atual
- `POST /api/auth/forgot-password` - Solicitar recuperação de senha
- `POST /api/auth/reset-password` - Redefinir senha
- `POST /api/auth/verify-email` - Verificar email
- `POST /api/auth/resend-verification` - Reenviar email de verificação

#### Estabelecimentos

- `GET /api/establishments` - Listar estabelecimentos
- `POST /api/establishments` - Criar estabelecimento
- `GET /api/establishments/{id}` - Obter estabelecimento
- `PUT /api/establishments/{id}` - Atualizar estabelecimento
- `DELETE /api/establishments/{id}` - Deletar estabelecimento

#### Serviços

- `GET /api/services` - Listar serviços
- `POST /api/services` - Criar serviço
- `GET /api/services/{id}` - Obter serviço
- `PUT /api/services/{id}` - Atualizar serviço
- `DELETE /api/services/{id}` - Deletar serviço

#### Agendamentos

- `GET /api/schedulings` - Listar agendamentos
- `POST /api/schedulings` - Criar agendamento
- `GET /api/schedulings/{id}` - Obter agendamento
- `PUT /api/schedulings/{id}` - Atualizar agendamento
- `DELETE /api/schedulings/{id}` - Deletar agendamento

#### Funcionários

- `GET /api/employees` - Listar funcionários
- `POST /api/employees` - Criar funcionário
- `GET /api/employees/{id}` - Obter funcionário
- `PUT /api/employees/{id}` - Atualizar funcionário
- `DELETE /api/employees/{id}` - Deletar funcionário

#### Dashboard

- `GET /api/dashboard/stats` - Estatísticas gerais
- `GET /api/dashboard/revenue-chart` - Dados do gráfico de receita
- `GET /api/dashboard/top-services` - Top serviços por receita

## 🚢 Deploy

### Backend

1. Configure o servidor web (Apache/Nginx) para apontar para `backend/public`
2. Configure as variáveis de ambiente no servidor
3. Execute `composer install --optimize-autoloader --no-dev`
4. Execute `php artisan migrate --force`
5. Execute `php artisan config:cache`
6. Execute `php artisan route:cache`
7. Configure o cron para `php artisan schedule:run` (a cada minuto)
8. Configure o queue worker: `php artisan queue:work --daemon`

### Frontend

1. Execute `ng build --configuration production`
2. Os arquivos estarão em `frontend/dist/`
3. Configure o servidor web para servir os arquivos estáticos
4. Configure o proxy reverso para a API se necessário

## 📝 Notas Importantes

### Filas (Queue)

Para desenvolvimento, use `QUEUE_CONNECTION=sync` no `.env` para processar emails imediatamente.

Para produção, use `QUEUE_CONNECTION=database` e mantenha `php artisan queue:work` rodando.

### Agendamento de Tarefas

O sistema envia lembretes de agendamento automaticamente:

- **24h antes**: Enviado diariamente às 08:00
- **1h antes**: Enviado a cada hora

Configure o cron no servidor:

```bash
* * * * * cd /path-to-project/backend && php artisan schedule:run >> /dev/null 2>&1
```

### Segurança

- ✅ Use HTTPS em produção
- ✅ Configure CORS adequadamente
- ✅ Use senhas fortes no banco de dados
- ✅ Mantenha as dependências atualizadas
- ✅ Configure rate limiting em produção

## 🤝 Contribuindo

1. Faça um fork do projeto
2. Crie uma branch para sua feature (`git checkout -b feature/AmazingFeature`)
3. Commit suas mudanças (`git commit -m 'Add some AmazingFeature'`)
4. Push para a branch (`git push origin feature/AmazingFeature`)
5. Abra um Pull Request

## 📄 Licença

Este projeto está sob a licença MIT.

## 👨‍💻 Autor

Desenvolvido com ❤️ para facilitar a gestão de salões de beleza.

---

**Versão:** 1.0.0  
**Última atualização:** Novembro 2025
