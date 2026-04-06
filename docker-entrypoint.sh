#!/bin/bash
set -e

echo "🚀 Starting Krayin CRM..."

# Criar estrutura de diretórios se não existir
echo "📁 Creating storage directories..."
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/framework/testing
mkdir -p storage/logs
mkdir -p storage/app/public
mkdir -p bootstrap/cache

# Definir permissões
echo "🔒 Setting permissions..."
chmod -R 775 storage
chmod -R 775 bootstrap/cache

# Limpar caches antigos
echo "🧹 Clearing old caches..."
php artisan cache:clear || true
php artisan config:clear || true
php artisan view:clear || true
php artisan route:clear || true

# Verificar se APP_KEY existe
if [ -z "$APP_KEY" ]; then
    echo "⚠️  APP_KEY not set, generating..."
    php artisan key:generate
fi

# Executar migrations (se necessário)
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "🗄️  Running migrations..."
    php artisan migrate --force
fi

echo "✅ Setup complete! Starting server..."

# Iniciar servidor
exec php artisan serve --host=0.0.0.0 --port=8000
