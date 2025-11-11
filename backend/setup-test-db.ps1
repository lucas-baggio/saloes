# Script PowerShell para configurar o banco de dados de testes

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Configurando Banco de Dados de Testes" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Verificar se o MySQL está acessível
Write-Host "[1/3] Verificando conexão com MySQL..." -ForegroundColor Yellow
$mysqlTest = mysql -u root -p58102099 -e "SELECT 1;" 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Host "ERRO: Nao foi possivel conectar ao MySQL. Verifique se o MySQL esta rodando." -ForegroundColor Red
    Read-Host "Pressione Enter para sair"
    exit 1
}
Write-Host "MySQL esta rodando!" -ForegroundColor Green
Write-Host ""

# Criar banco de dados de teste
Write-Host "[2/3] Criando banco de dados de teste..." -ForegroundColor Yellow
$createDb = mysql -u root -p58102099 -e "CREATE DATABASE IF NOT EXISTS sl_db_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Host "ERRO: Nao foi possivel criar o banco de dados." -ForegroundColor Red
    Write-Host $createDb -ForegroundColor Red
    Read-Host "Pressione Enter para sair"
    exit 1
}
Write-Host "Banco de dados criado com sucesso!" -ForegroundColor Green
Write-Host ""

# Executar migrations
Write-Host "[3/3] Executando migrations no banco de teste..." -ForegroundColor Yellow
php artisan migrate --database=mysql --env=testing --force
if ($LASTEXITCODE -ne 0) {
    Write-Host "ERRO: Nao foi possivel executar as migrations." -ForegroundColor Red
    Read-Host "Pressione Enter para sair"
    exit 1
}
Write-Host "Migrations executadas com sucesso!" -ForegroundColor Green
Write-Host ""

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Banco de dados de teste configurado!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Agora voce pode executar os testes com:" -ForegroundColor Yellow
Write-Host "  php artisan test" -ForegroundColor White
Write-Host ""
Read-Host "Pressione Enter para sair"

