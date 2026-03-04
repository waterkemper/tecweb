# tecDESK

Internal app that syncs Zendesk tickets (with full conversation), provides search/filtering, and runs AI classification, extraction, effort estimation, and duplicate detection. Includes authentication (admin, colaborador, cliente) and role-based ticket visibility.

## Requirements

- PHP 8.2+
- PostgreSQL 17+ with [pgvector](https://github.com/pgvector/pgvector) extension
- Redis
- Composer

## Docker

### Production-like (recommended)

```bash
cp .env.example .env
# Edit .env: APP_KEY, DB_*, ZENDESK_*, OPENAI_API_KEY, APP_URL=http://localhost:8080
php artisan key:generate  # or set APP_KEY in .env

docker compose up -d --build
# App: http://localhost:8080
# Migrations run automatically on first start

# Create admin
docker compose exec app php artisan user:create-admin admin@example.com --password=secret

# Sync Zendesk
docker compose exec app php artisan zendesk:sync --full
docker compose exec app php artisan zendesk:sync-users-app
```

### Development (live reload)

```bash
npm run build  # build assets first
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --build
```

### Services

| Service | Port | Description |
|---------|------|-------------|
| app | 8080 | Laravel + nginx + queue + scheduler |
| postgres | 5432 | PostgreSQL 17 with pgvector |
| redis | 6379 | Redis 7 |

## Setup (without Docker)

1. **Clone and install**
   ```bash
   composer install
   cp .env.example .env
   php artisan key:generate
   ```

2. **Configure `.env`**
   - `DB_*` - PostgreSQL connection
   - `REDIS_*` - Redis (for queue)
   - `QUEUE_CONNECTION=redis`
   - `CACHE_STORE=redis`
   - `ADMIN_EMAIL`, `ADMIN_PASSWORD` - Admin user (for seeder)
   - `ZENDESK_SUBDOMAIN`, `ZENDESK_EMAIL`, `ZENDESK_API_TOKEN`
   - `OPENAI_API_KEY`

3. **Database**
   ```bash
   # Create PostgreSQL database with pgvector
   createdb zendesk_ai
   psql zendesk_ai -c "CREATE EXTENSION IF NOT EXISTS vector;"
   php artisan migrate
   ```

4. **Create admin user**
   ```bash
   php artisan db:seed --class=AdminUserSeeder
   # Or: php artisan user:create-admin email@example.com --password=yourpassword
   ```

5. **Sync Zendesk**
   ```bash
   # Full initial backfill (tickets + comments + users + orgs)
   php artisan zendesk:sync --full
   php artisan zendesk:sync-users-app
   # Optional: set default password for synced users
   php artisan zendesk:sync-users-app --password=changeme
   ```

6. **Process AI**
   ```bash
   php artisan zendesk:process-ai --limit=20
   # Process all pending at once:
   php artisan zendesk:process-ai --no-limit
   # Reprocess ALL tickets (e.g. after adding new AI fields like pending_action):
   php artisan zendesk:process-ai --all --limit=100
   ```

7. **Queue worker** (required for jobs)
   ```bash
   php artisan queue:work
   ```

8. **Scheduler** (for incremental sync)
   ```bash
   php artisan schedule:work
   ```

## Commands

| Command | Description |
|---------|-------------|
| `zendesk:sync` | Incremental sync (tickets, users, orgs) |
| `zendesk:sync --full` | Full initial backfill |
| `zendesk:sync --tickets-only` | Sync tickets only |
| `zendesk:process-ai` | Run AI for tickets needing refresh (10 at a time) |
| `zendesk:process-ai --no-limit` | Process all pending tickets at once |
| `zendesk:process-ai --all` | Reprocess all tickets (use after adding new AI fields) |
| `zendesk:sync-users-app` | Create User for every ZdUser (temp password) |
| `zendesk:sync-users-app --password=X` | Temp password (default: changeme) |
| `zendesk:sync-users-app --force` | Reset existing users' password to temp |
| `user:create-admin {email}` | Create or update admin user |
| `user:create-admin {email} --password=X` | Set password (or prompted interactively) |
| `user:set-password {email}` | Set/change user password (prompted) |
| `user:set-password {email} --password=X` | Set password via option |

## Screens

- **Login** - tecDESK branding, email/password auth, "Esqueci minha senha" → recuperação por email
- **Dashboard** - Stats (New/Open/Pending, hours predicted, **Atrasados**, **Sem prazo**), by organization, by requester (admin), by severity/category, tickets-by-date chart, **Tickets atrasados** table, high-severity queue. Role-based: admin/colaborador see full stats; cliente sees only their tickets.
- **Tickets** - List with search, filters (incl. org, **Atrasados**, **Sem prazo**, prazo De/Até), **Prazo** column with overdue/upcoming badges. Order by sequence (per requester) or by prazo; drag-and-drop to reorder; new synced tickets get next sequence automatically.
- **Ticket Detail** - People involved (requester, submitter, assignee, org, collaborators) for admin/colaborador; **Prazo de entrega** (editável por admin/colaborador); full conversation; AI panel; **Refresh IA** button (admin only); similar tickets

## Architecture

- **Sync**: Cursor-based incremental ticket export, comments per ticket. Tickets with unchanged `zd_updated_at` skip FetchTicketCommentsJob.
- **AI**: OpenAI for classification (GPT-4o-mini) and embeddings (text-embedding-3-small). `content_hash` (subject+description+comments) avoids reprocessing unchanged tickets.
- **Similarity**: pgvector cosine distance, duplicate boost for same requester + 7 days
- **PII**: CPF/CNPJ, email, phone redacted before AI prompts
