@echo off
REM Script para configurar o banco de dados de testes no Windows

echo ========================================
echo Configurando Banco de Dados de Testes
echo ========================================
echo.

echo [1/2] Criando banco de dados de teste...
mysql -u root -p58102099 -e "CREATE DATABASE IF NOT EXISTS sl_db_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>nul
if %errorlevel% neq 0 (
    echo ERRO: Nao foi possivel criar o banco de dados. Verifique se o MySQL esta rodando.
    pause
    exit /b 1
)
echo Banco de dados criado com sucesso!
echo.

echo [2/2] Executando migrations no banco de teste...
php artisan migrate --database=mysql --env=testing --force
if %errorlevel% neq 0 (
    echo ERRO: Nao foi possivel executar as migrations.
    pause
    exit /b 1
)
echo Migrations executadas com sucesso!
echo.

echo ========================================
echo Banco de dados de teste configurado!
echo ========================================
echo.
echo Agora voce pode executar os testes com:
echo   php artisan test
echo.
pause

