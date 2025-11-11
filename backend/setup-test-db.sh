#!/bin/bash

# Script para configurar o banco de dados de testes

echo "Criando banco de dados de teste..."
mysql -u root -p58102099 -e "CREATE DATABASE IF NOT EXISTS sl_db_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "Executando migrations no banco de teste..."
php artisan migrate --database=mysql --env=testing --force

echo "Banco de dados de teste configurado com sucesso!"

