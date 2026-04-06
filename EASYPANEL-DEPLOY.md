# Guia de Deploy no Easypanel

## 📋 Variáveis de Ambiente Recomendadas

```env
# Application
APP_NAME='MEBUKINHO 3.0'
APP_ENV=production
APP_DEBUG=false                    # ⚠️ SEMPRE false em produção!
APP_URL=https://criadordigital-krayin-crm-oficial.qs06zq.easypanel.host
APP_TIMEZONE=America/Sao_Paulo
APP_LOCALE=pt_BR
APP_CURRENCY=BRL
APP_KEY=base64:22+Vb404KaGz6mhj94UHag+YPoAo5OYJJj3vvscp0qc=

# Database
DB_CONNECTION=mysql
DB_HOST=krayin-db
DB_PORT=3306
DB_DATABASE=krayin
DB_USERNAME=mariadb
DB_PASSWORD=fc1a32b902a9ccc9f3cd

# Cache & Sessions
CACHE_DRIVER=file
SESSION_DRIVER=file
SESSION_LIFETIME=600
FILESYSTEM_DISK=public
QUEUE_CONNECTION=sync

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=error                    # Use 'error' em produção, não 'debug'
BROADCAST_DRIVER=log

# Mail
MAIL_FROM_ADDRESS="aplicativos.mebuki@gmail.com"
MAIL_FROM_NAME="Mebuki"

# Chatwoot Integration
CHATWOOT_API_URL=https://financeiro-chatwoot-mebuki.qs06zq.easypanel.host
CHATWOOT_API_KEY=WVvtLKSNSzkkp7wbcyLEskbH
CHATWOOT_ACCOUNT_ID=1
CHATWOOT_BRIDGE_SECRET=253847*Kra

# Optional: Run migrations on startup
RUN_MIGRATIONS=false               # Defina como 'true' se quiser migrations automáticas
```

## 🚀 Processo de Deploy

1. **Configure as variáveis de ambiente** no Easypanel (use as recomendadas acima)
2. **Clique em "Rebuild"** para reconstruir a imagem Docker
3. **Aguarde o build completar** (pode levar 2-5 minutos)
4. **Verifique os logs** para confirmar que o setup foi concluído:
   - Deve aparecer: `✅ Setup complete! Starting server...`
5. **Acesse a aplicação** pela URL configurada

## 🔍 Troubleshooting

### Erro: "Please provide a valid cache path"

**Causa**: Diretórios de storage não existem ou não têm permissões corretas

**Solução**:
1. Force rebuild no Easypanel
2. Verifique se o entrypoint está sendo executado (veja logs)
3. Se persistir, execute manualmente no container:
   ```bash
   docker exec -it <container-name> bash /var/www/html/fix-storage.sh
   ```

### Erro: "No application encryption key has been specified"

**Causa**: APP_KEY não está definida ou é inválida

**Solução**:
1. Gere uma nova chave localmente: `php artisan key:generate --show`
2. Adicione nas variáveis de ambiente do Easypanel
3. Rebuild

### Performance lenta

**Causa**: APP_DEBUG=true em produção

**Solução**:
1. Defina `APP_DEBUG=false`
2. Defina `LOG_LEVEL=error`
3. Rebuild

## 📝 Notas Importantes

- ✅ O `docker-entrypoint.sh` cria automaticamente todos os diretórios necessários
- ✅ Permissões são configuradas automaticamente (775)
- ✅ Caches são limpos a cada restart do container
- ⚠️ **NUNCA** use `APP_DEBUG=true` em produção (expõe informações sensíveis)
- ⚠️ **SEMPRE** use `LOG_LEVEL=error` em produção (não `debug`)
