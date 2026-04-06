#!/bin/bash

echo "🔧 Corrigindo estrutura de storage e permissões..."

# Criar diretórios necessários
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/framework/testing
mkdir -p storage/logs
mkdir -p storage/app/public
mkdir -p bootstrap/cache

echo "✅ Diretórios criados"

# Definir permissões corretas
chmod -R 775 storage
chmod -R 775 bootstrap/cache

echo "✅ Permissões configuradas (775)"

# Limpar caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear

echo "✅ Caches limpos"

# Verificar estrutura
echo ""
echo "📂 Estrutura final:"
ls -la storage/framework/

echo ""
echo "✨ Correção concluída!"
