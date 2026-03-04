# Deploy tecDESK no EC2 - tecdesk.iteclux.com.br

Guia passo a passo para deploy da aplicação tecDESK em Docker em servidor EC2 existente, usando PostgreSQL 17 já instalado no host e Apache como reverse proxy.

**Cenário:** Servidor com sites e-commerce em Apache, outro app em Docker, PostgreSQL 17 no host.

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

# Redis (usa o do container)
REDIS_HOST=redis
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

Isso sobe apenas **app** (porta 8080) e **redis**. O PostgreSQL é o do host.

**Conflito de porta:** Se a porta 6379 já estiver em uso, altere no `docker-compose.prod.yml`:

```yaml
redis:
  ports:
    - "6380:6379"  # host:container
```

E no `.env` do Laravel, se o app precisar acessar Redis de fora do Docker, use `REDIS_PORT=6380`. Dentro do Docker, o app usa `redis:6379` (nome do serviço).

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

## 5. Apache – VirtualHost e SSL

### 5.1 Habilitar módulos de proxy

```bash
sudo a2enmod proxy proxy_http proxy_wstunnel headers ssl
sudo systemctl reload apache2
```

### 5.2 Criar VirtualHost

Crie o arquivo de configuração (ex.: `/etc/apache2/sites-available/tecdesk.iteclux.com.br.conf`):

```apache
<VirtualHost *:80>
    ServerName tecdesk.iteclux.com.br

    ProxyPreserveHost On
    ProxyPass / http://127.0.0.1:8080/
    ProxyPassReverse / http://127.0.0.1:8080/

    ErrorLog ${APACHE_LOG_DIR}/tecdesk-error.log
    CustomLog ${APACHE_LOG_DIR}/tecdesk-access.log combined
</VirtualHost>
```

### 5.3 Ativar o site

```bash
sudo a2ensite tecdesk.iteclux.com.br.conf
sudo systemctl reload apache2
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
    → ProxyPass → localhost:8080
    → Container tecdesk-app (Nginx + PHP-FPM)
    → Laravel
```

O Nginx roda apenas **dentro** do container. No host, o Apache faz o proxy para o container.
