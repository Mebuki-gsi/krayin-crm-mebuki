# Troubleshooting Easypanel Permission Issues

## Current Issue: Permission Denied on storage/framework/views

### Quick Fix Option 1: Check Container Logs

Após rebuild, acesse os logs do container no Easypanel e procure por:
```
🔍 Checking storage/framework/views...
🧪 Testing write permissions...
```

Se ver `❌ Write test FAILED`, o problema é que o Easypanel está montando um volume read-only ou com usuário incorreto.

### Quick Fix Option 2: Desabilitar Cache de Views Temporariamente

Adicione esta variável de ambiente no Easypanel:
```
VIEW_CACHE_DISABLED=true
```

Depois faça rebuild. Isso desabilita o cache de views e permite que a aplicação funcione (com performance um pouco reduzida).

### Quick Fix Option 3: Verificar Configuração de Volumes no Easypanel

No Easypanel, verifique se há algum volume montado em `/var/www/storage`. Se houver:
1. Remova o volume (cuidado: isso apaga dados)
2. OU: Mude o mount point para `/var/www/storage-data` e ajuste a aplicação

### Quick Fix Option 4: Executar Comando Manualmente

No terminal do Easypanel, entre no container e execute:
```bash
chmod -R 777 /var/www/storage
chmod -R 777 /var/www/bootstrap/cache
```

Se isso funcionar, o problema é que o entrypoint não está rodando.

### Diagnóstico Detalhado

**Execute no container (via terminal do Easypanel):**
```bash
# Verificar usuário
whoami

# Verificar permissões
ls -la /var/www/storage/framework/views

# Verificar se consegue criar arquivo
touch /var/www/storage/framework/views/test.php
rm /var/www/storage/framework/views/test.php

# Verificar owner
stat /var/www/storage/framework/views

# Verificar se é um volume
mount | grep storage
```

### Solução Permanente

Se o problema for volumes do Easypanel, você precisa:

1. **Remover volumes persistentes** (se não precisar manter dados):
   - No Easypanel, vá em "Volumes"
   - Remova qualquer volume montado em `/var/www/storage`

2. **OU ajustar o Dockerfile** para usar um volume específico:
   - Criar um volume separado apenas para uploads: `/var/www/storage/app`
   - Deixar cache/views/sessions como efêmeros (sem volume)

3. **OU usar cache Redis/Memcached** em vez de file cache

## Variáveis de Ambiente de Emergência

Se nada mais funcionar, adicione estas variáveis no Easypanel:

```env
# Desabilita cache de views (usa compilação on-the-fly)
VIEW_CACHE_DISABLED=true

# Muda cache para array (em memória, sem persistência)
CACHE_DRIVER=array
SESSION_DRIVER=array
```

**Atenção**: Isso faz a aplicação funcionar mas:
- Performance será menor
- Sessões serão perdidas em cada restart
- Cache não persistirá entre requests

## Contato

Se o problema persistir após todas essas tentativas, o issue pode estar na configuração específica do Easypanel. Considere:
- Usar Docker Compose local para testar
- Contatar suporte do Easypanel sobre permissões de volumes
- Migrar para outro provider (Render, Railway, Fly.io)
