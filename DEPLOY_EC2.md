# Deploy tecDESK no EC2 - tecdesk.iteclux.com.br

Guia passo a passo para deploy da aplicação tecDESK em Docker em servidor EC2 existente, usando PostgreSQL 17 já instalado no host e Apache como reverse proxy.

**Cenário:** Servidor com sites e-commerce em Apache, outro app em Docker, PostgreSQL 17 e Redis já instalados no host.

---

## Testar localmente (produção)

Para validar o setup antes do deploy no EC2:

```bash
cp .env.example .env
# Edite .env: DB_HOST, DB_PORT=5437, REDIS_HOST=host.docker.internal, REDIS_PORT=6379, DB_*, ZENDESK_*, OPENAI_*
php artisan key:generate  # ou defina APP_KEY no .env

docker compose -f docker-compose.prod.yml up -d --build
# App em http://localhost:8081
```

PostgreSQL (porta 5437) e Redis (porta 6379) devem estar rodando no host. No Windows/Mac, `host.docker.internal` funciona nativamente; no Linux, o `docker-compose.prod.yml` já inclui `extra_hosts`.

---

## 1. DNS

Se o subdomínio ainda não existir, crie o registro:

- **Tipo:** A ou CNAME
- **Nome:** `tecdesk` (ou `tecdesk.iteclux` conforme seu provedor)
- **Valor:** IP público do servidor EC2

Aguarde a propagação (geralmente alguns minutos).

---

## 2. Preparar o projeto no servidor

### 2.1 Clonar ou enviar o código

```bash
# Exemplo com git
cd /var/www  # ou o diretório onde ficam seus projetos
git clone <url-do-repositorio> tecdesk
cd tecdesk
```

Ou envie os arquivos via SCP/rsync a partir da sua máquina.

### 2.2 Criar o arquivo .env

```bash
cp .env.example .env
nano .env  # ou vim
```

Configure as variáveis principais:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tecdesk.iteclux.com.br

# PostgreSQL (já instalado no host, porta 5437)
DB_CONNECTION=pgsql
DB_HOST=host.docker.internal
DB_PORT=5437
DB_DATABASE=zendesk_ai
DB_USERNAME=seu_usuario
DB_PASSWORD=sua_senha

# Redis (já instalado no host)
REDIS_HOST=host.docker.internal
REDIS_PORT=6379
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=database

# Zendesk
ZENDESK_SUBDOMAIN=seu_subdominio
ZENDESK_EMAIL=seu_email
ZENDESK_API_TOKEN=seu_token

# OpenAI
OPENAI_API_KEY=sua_chave
```

**Importante:** O banco `zendesk_ai` deve existir no PostgreSQL e ter a extensão pgvector:

```sql
CREATE EXTENSION IF NOT EXISTS vector;
```

### 2.3 Gerar APP_KEY

```bash
docker compose -f docker-compose.prod.yml build
docker compose -f docker-compose.prod.yml run --rm app php artisan key:generate
```

O `.env` do host é montado no container; a chave gerada será salva no seu `.env`.

---

## 3. Subir os containers

```bash
docker compose -f docker-compose.prod.yml up -d --build
```

Isso sobe apenas **app** (porta 8081). PostgreSQL e Redis são do host.

**Conflito de porta:** Se 8081 também estiver em uso, altere no `docker-compose.prod.yml` (ex.: `"8082:80"`) e no VirtualHost do Apache (`ProxyPass` e `ProxyPassReverse`).

---

## 4. Configuração pós-deploy

### 4.1 Criar usuário admin

```bash
docker compose -f docker-compose.prod.yml exec app php artisan user:create-admin admin@example.com --password=suasenha
```

### 4.2 Sincronizar Zendesk (primeira vez)

```bash
docker compose -f docker-compose.prod.yml exec app php artisan zendesk:sync --full
docker compose -f docker-compose.prod.yml exec app php artisan zendesk:sync-users-app
```

---

## 5. Apache (httpd) – VirtualHost e SSL

> **Amazon Linux 2** usa `httpd` (não apache2). Caminhos e comandos abaixo são para essa distro.

### 5.1 Habilitar módulos de proxy

No Amazon Linux 2, os módulos `proxy` e `proxy_http` costumam vir habilitados. Verifique:

```bash
httpd -M | grep proxy
```

Se não aparecer `proxy_module` ou `proxy_http_module`, descomente as linhas em `/etc/httpd/conf.modules.d/00-base.conf` ou crie `/etc/httpd/conf.modules.d/01-proxy.conf` com:

```apache
LoadModule proxy_module modules/mod_proxy.so
LoadModule proxy_http_module modules/mod_proxy_http.so
```

Depois:

```bash
sudo systemctl reload httpd
```

### 5.2 Criar VirtualHost

Crie o arquivo em `/etc/httpd/conf.d/tecdesk.iteclux.com.br.conf`:

```apache
<VirtualHost *:80>
    ServerName tecdesk.iteclux.com.br

    ProxyPreserveHost On
    ProxyPass / http://127.0.0.1:8081/
    ProxyPassReverse / http://127.0.0.1:8081/

    ErrorLog /var/log/httpd/tecdesk-error.log
    CustomLog /var/log/httpd/tecdesk-access.log combined
</VirtualHost>
```

### 5.3 Recarregar o httpd

Arquivos em `/etc/httpd/conf.d/` são carregados automaticamente. Recarregue:

```bash
sudo systemctl reload httpd
```

### 5.4 SSL com Let's Encrypt

```bash
sudo certbot --apache -d tecdesk.iteclux.com.br
```

O Certbot ajusta o VirtualHost para HTTPS. A renovação automática já vem configurada.

---

## 6. Comandos úteis

| Ação | Comando |
|------|---------|
| Ver logs | `docker compose -f docker-compose.prod.yml logs -f app` |
| Reiniciar | `docker compose -f docker-compose.prod.yml restart app` |
| Parar | `docker compose -f docker-compose.prod.yml down` |
| Subir | `docker compose -f docker-compose.prod.yml up -d` |
| Sync incremental | `docker compose -f docker-compose.prod.yml exec app php artisan zendesk:sync` |
| Processar IA | `docker compose -f docker-compose.prod.yml exec app php artisan zendesk:process-ai --limit=20` |

---

## 7. Atualizações futuras

```bash
cd /var/www/tecdesk
git pull
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
```

---

## Fluxo da requisição

```
Internet (HTTPS)
    → Apache (80/443)
    → ProxyPass → localhost:8081
    → Container tecdesk-app (Nginx + PHP-FPM)
    → Laravel
```

O Nginx roda apenas **dentro** do container. No host, o Apache faz o proxy para o container.
