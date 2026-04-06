# Docker Storage Permission Issues - Documentação Técnica

## 📋 Resumo do Problema

Ao fazer deploy do Krayin CRM em plataformas como Easypanel, Railway, Render etc., ocorrem erros de permissão:

```
InvalidArgumentException: Please provide a valid cache path
ErrorException: file_put_contents(/var/www/storage/framework/views/...): Permission denied
```

## 🔍 Causa Raiz

O problema ocorre devido a uma **incompatibilidade entre build-time e runtime** quando volumes Docker são montados:

### Sequência do Problema:

1. **Durante o Docker build:**
   - Dockerfile cria `storage/framework/{views,cache,sessions}` com permissões 777
   - Diretórios existem e são graváveis na imagem Docker

2. **Durante o container runtime (Easypanel/Railway/etc):**
   - Plataforma monta um **volume em `/var/www`** (para persistir código)
   - Volume **sobrescreve** toda a estrutura criada no build
   - Permissões do volume são **diferentes** (read-only ou owner incorreto)
   - Laravel tenta escrever em `storage/framework/views` → **Permission denied**

### Por que `chmod 777` não funciona?

Tentativas de aplicar `chmod` no entrypoint/bootstrap falham porque:
- Volume já está montado com permissões fixas
- `chmod` pode não ter efeito em alguns tipos de volume (bind mounts, NFS)
- Usuário do container pode não ter permissão para mudar owner/permissions

## 🛠️ Histórico de Tentativas de Solução

### ❌ Tentativa 1-2: Dockerfile + Build Time
```dockerfile
RUN mkdir -p storage/framework/views && chmod -R 777 storage
```
**Resultado:** Falhava porque volume mount sobrescreve tudo no runtime

### ❌ Tentativa 3-4: Entrypoint Script
```bash
chmod -R 777 /var/www/storage
```
**Resultado:** Ou não executava, ou não tinha permissão para alterar volume

### ❌ Tentativa 5-6: Service Provider + Bootstrap
```php
@mkdir(storage_path('framework/views'), 0777, true);
@chmod(storage_path('framework/views'), 0777);
```
**Resultado:** Criava diretórios mas `chmod` era ignorado

### ✅ Solução Final: Fallback Automático para `/tmp`

Implementado em `config/view.php`, `config/cache.php`, `config/session.php`:

```php
'compiled' => (function() {
    // Tenta usar storage primeiro
    $path = storage_path('framework/views');
    
    if (!is_dir($path)) {
        @mkdir($path, 0777, true);
    }
    
    // Testa se é gravável
    $testFile = $path . '/.write_test_' . time();
    if (@file_put_contents($testFile, 'test') === false) {
        // Fallback para /tmp (sempre gravável)
        $path = '/tmp/laravel_views';
        if (!is_dir($path)) {
            @mkdir($path, 0777, true);
        }
    } else {
        @unlink($testFile);
    }
    
    return $path;
})()
```

**Por que funciona:**
- `/tmp` é sempre gravável em containers Linux
- Testa automaticamente antes de usar
- Fallback transparente, sem erro
- Não requer configuração adicional

## 📊 Comparação: Storage vs /tmp

| Aspecto | `/var/www/storage` | `/tmp` (fallback) |
|---------|-------------------|-------------------|
| **Persistência** | ✅ Persiste entre restarts | ❌ Perdido ao reiniciar |
| **Performance** | ✅ Normal | ⚠️ Primeiro request lento após restart |
| **Disponibilidade** | ⚠️ Depende de permissões | ✅ Sempre disponível |
| **Requer setup** | ⚠️ Sim (permissões corretas) | ✅ Não |
| **Uploads** | ✅ Adequado | ❌ Não recomendado |

## 🔧 Diagnóstico de Problemas

### 1. Verificar se o fallback está ativo

Adicione logs no entrypoint (`docker-entrypoint.sh`):

```bash
echo "🔍 Checking storage permissions..."
ls -la /var/www/storage/framework/views
stat /var/www/storage/framework/views

echo "🧪 Testing write permissions..."
touch /var/www/storage/framework/views/.test 2>/dev/null && \
  rm /var/www/storage/framework/views/.test && \
  echo "✅ Storage is WRITABLE" || \
  echo "❌ Storage is NOT writable - using /tmp fallback"
```

### 2. Verificar onde os arquivos estão sendo gravados

Execute no container:

```bash
# Ver arquivos de views compiladas
ls -la /var/www/storage/framework/views/
ls -la /tmp/laravel_views/

# Ver qual está sendo usado
php artisan tinker
>>> config('view.compiled')
```

### 3. Verificar permissões do volume

```bash
# No container
mount | grep /var/www
ls -ld /var/www/storage/framework/views
stat /var/www/storage/framework/views

# Verificar usuário
whoami
id
```

## ✅ Soluções Permanentes

### Opção 1: Remover Volume Mount do Storage (Recomendado)

No Easypanel/Docker Compose, evite montar volumes em `/var/www/storage`:

```yaml
# ❌ NÃO faça isso:
volumes:
  - ./storage:/var/www/storage

# ✅ Monte apenas uploads:
volumes:
  - ./storage/app:/var/www/storage/app
```

**Prós:**
- Cache/views/sessions podem usar storage normal
- Melhor performance
- Sem restart penalties

**Contras:**
- Cache perdido em rebuilds (aceitável)

### Opção 2: Usar Cache/Session em Memória (Redis/Memcached)

Configure no `.env`:

```env
CACHE_DRIVER=redis
SESSION_DRIVER=redis
REDIS_HOST=redis
REDIS_PORT=6379
```

**Prós:**
- Melhor performance que file cache
- Não depende de filesystem
- Escalável (múltiplos containers)

**Contras:**
- Requer serviço Redis adicional
- Mais complexo

### Opção 3: Ajustar Permissões do Volume

No `docker-compose.yml` ou Easypanel, configure o container para rodar como root:

```yaml
user: "0:0"  # root:root
```

**Prós:**
- Storage funciona normalmente

**Contras:**
- Menos seguro (container como root)
- Não recomendado em produção

### Opção 4: Init Container / Volume Permissions

Criar um init container que ajusta permissões antes do app iniciar:

```yaml
initContainers:
  - name: fix-permissions
    image: busybox
    command: ['sh', '-c', 'chmod -R 777 /var/www/storage']
    volumeMounts:
      - name: storage
        mountPath: /var/www/storage
```

**Prós:**
- Garante permissões corretas

**Contras:**
- Funciona apenas em Kubernetes
- Não disponível no Easypanel

## 🎯 Recomendação Final

### Para Produção:

1. **Use Redis/Memcached** para cache e sessions
2. **Monte volume apenas em `/var/www/storage/app`** (uploads)
3. **Deixe views/cache/sessions em `/tmp`** ou na imagem Docker

### Para Desenvolvimento:

1. **Use a solução `/tmp` fallback** (já implementada)
2. **Aceite cache loss em restarts** (não é problema)
3. **Monitore logs** para confirmar que fallback está ativo

## 📝 Variáveis de Ambiente Relacionadas

```env
# Produção (recomendado)
CACHE_DRIVER=redis
SESSION_DRIVER=redis
REDIS_HOST=redis

# Desenvolvimento (funciona com /tmp fallback)
CACHE_DRIVER=file
SESSION_DRIVER=file

# Desabilitar DEBUG em produção (não relacionado mas importante!)
APP_DEBUG=false
LOG_LEVEL=error
```

## 🔗 Arquivos Relacionados

- `config/view.php` - Fallback para `/tmp/laravel_views`
- `config/cache.php` - Fallback para `/tmp/laravel_cache`
- `config/session.php` - Fallback para `/tmp/laravel_sessions`
- `bootstrap/app.php` - Criação antecipada de diretórios
- `docker-entrypoint.sh` - Debugging e setup
- `Dockerfile` - Build-time setup
- `EASYPANEL-TROUBLESHOOTING.md` - Guia de troubleshooting específico

## 📅 Histórico

- **2026-04-06**: Problema identificado e resolvido com fallback `/tmp`
- **Commits**: `5a18e50d`, `3e01fa8d`, `5ae8f3d6`, `7409a6b7`
- **Branch**: `mebuki-crm`

## 👥 Créditos

Documentação criada após debugging e resolução de problemas de permissão em deploy Docker/Easypanel.

---

**💡 Lembre-se:** Se você ver `Permission denied` novamente, verifique os logs para confirmar que o fallback `/tmp` está ativo. Se estiver usando `/tmp`, a aplicação está funcionando corretamente - apenas aceite a perda de cache em restarts ou migre para Redis.
