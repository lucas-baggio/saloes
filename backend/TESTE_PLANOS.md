# Como Testar os Limites de Planos

## Sistema Implementado

Os limites de planos foram implementados e estão funcionando para:
- **Estabelecimentos** (`max_establishments`)
- **Serviços** (`max_services`)
- **Funcionários** (`max_employees`)

## Como Funciona

1. Quando um usuário tenta criar um estabelecimento, serviço ou adicionar um funcionário, o sistema verifica:
   - Se o usuário tem um plano ativo
   - Se o plano tem limite definido (null = ilimitado)
   - Quantos itens o usuário já possui
   - Se o limite foi atingido

2. Se o limite foi atingido, a operação é bloqueada e uma mensagem de erro é retornada.

## Testando via API

### 1. Criar um usuário de teste com plano

```bash
POST /api/test/create-test-user
Content-Type: application/json

{
  "plan_id": 1,  // ID do plano básico (limite: 1 estabelecimento, 10 serviços, 3 funcionários)
  "name": "Usuário Teste",
  "email": "teste@example.com"
}
```

**Resposta:**
```json
{
  "user": {
    "id": 1,
    "name": "Usuário Teste",
    "email": "teste@example.com",
    "password": "password"
  },
  "plan": {
    "id": 1,
    "name": "Básico",
    "max_establishments": 1,
    "max_services": 10,
    "max_employees": 3
  },
  "limits": {
    "has_plan": true,
    "plan_name": "Básico",
    "max_establishments": 1,
    "max_services": 10,
    "max_employees": 3,
    "current_establishments": 0,
    "current_services": 0,
    "current_employees": 0
  },
  "can_create_establishment": {
    "allowed": true,
    "current": 0,
    "limit": 1,
    "remaining": 1
  },
  "can_create_service": {
    "allowed": true,
    "current": 0,
    "limit": 10,
    "remaining": 10
  },
  "can_add_employee": {
    "allowed": true,
    "current": 0,
    "limit": 3,
    "remaining": 3
  }
}
```

### 2. Fazer login com o usuário de teste

```bash
POST /api/auth/login
Content-Type: application/json

{
  "email": "teste@example.com",
  "password": "password"
}
```

### 3. Testar criar estabelecimento (deve funcionar)

```bash
POST /api/establishments
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Meu Estabelecimento",
  "description": "Descrição do estabelecimento"
}
```

### 4. Testar criar segundo estabelecimento (deve falhar)

```bash
POST /api/establishments
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Segundo Estabelecimento",
  "description": "Descrição"
}
```

**Resposta esperada (403 Forbidden):**
```json
{
  "message": "Você atingiu o limite de estabelecimentos do seu plano (1). Faça upgrade para criar mais estabelecimentos.",
  "current": 1,
  "limit": 1
}
```

### 5. Verificar limites atuais

```bash
GET /api/test/plan-limits/{userId}
```

### 6. Testar criar serviços

Criar serviços até atingir o limite (10 serviços para o plano básico).

### 7. Testar adicionar funcionários

Adicionar funcionários até atingir o limite (3 funcionários para o plano básico).

## Testando via Teste Unitário

Execute os testes PHPUnit:

```bash
cd backend
php artisan test --filter PlanLimitsTest
```

Ou execute um teste específico:

```bash
php artisan test --filter test_establishment_limits
php artisan test --filter test_service_limits
php artisan test --filter test_employee_limits
php artisan test --filter test_unlimited_plan
```

## Planos Disponíveis

### Plano Básico (ID: 1)
- **Estabelecimentos:** 1
- **Serviços:** 10
- **Funcionários:** 3
- **Preço:** R$ 29,90/mês

### Plano Profissional (ID: 2)
- **Estabelecimentos:** 3
- **Serviços:** 50
- **Funcionários:** 10
- **Preço:** R$ 79,90/mês

### Plano Premium (ID: 3)
- **Estabelecimentos:** Ilimitado
- **Serviços:** Ilimitado
- **Funcionários:** Ilimitado
- **Preço:** R$ 149,90/mês

## Notas Importantes

1. **Admin não tem limites:** Usuários com role `admin` podem criar quantos estabelecimentos, serviços e funcionários quiserem.

2. **Plano ilimitado:** Quando `max_establishments`, `max_services` ou `max_employees` é `null`, o limite é ilimitado.

3. **Contagem de funcionários:** A contagem de funcionários é feita por funcionário único. Se um funcionário trabalha em múltiplos estabelecimentos do mesmo owner, ele conta apenas uma vez.

4. **Rotas de teste:** As rotas de teste (`/api/test/*`) só estão disponíveis em ambiente `local` ou `testing`. Em produção, essas rotas não existem.

## Limpeza

Após os testes, você pode limpar os dados de teste:

```sql
-- Remover usuários de teste
DELETE FROM users WHERE email LIKE 'teste%@teste.com';

-- Remover planos de teste
DELETE FROM user_plans WHERE user_id IN (SELECT id FROM users WHERE email LIKE 'teste%@teste.com');

-- Remover estabelecimentos de teste
DELETE FROM establishments WHERE owner_id IN (SELECT id FROM users WHERE email LIKE 'teste%@teste.com');
```

