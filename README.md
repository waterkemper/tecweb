# Zendesk AI Support App

Internal app that syncs Zendesk tickets (with full conversation), provides search/filtering, and runs AI classification, extraction, effort estimation, and duplicate detection.

## Requirements

- PHP 8.2+
- PostgreSQL 16+ with [pgvector](https://github.com/pgvector/pgvector) extension
- Redis
- Composer

## Setup

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
   - `ZENDESK_SUBDOMAIN`, `ZENDESK_EMAIL`, `ZENDESK_API_TOKEN`
   - `OPENAI_API_KEY`

3. **Database**
   ```bash
   # Create PostgreSQL database with pgvector
   createdb zendesk_ai
   psql zendesk_ai -c "CREATE EXTENSION IF NOT EXISTS vector;"
   php artisan migrate
   ```

4. **Sync Zendesk**
   ```bash
   # Full initial backfill (tickets + comments + users + orgs)
   php artisan zendesk:sync --full
   ```

5. **Process AI**
   ```bash
   php artisan zendesk:process-ai --limit=20
   # Reprocess ALL tickets (e.g. after adding new AI fields like pending_action):
   php artisan zendesk:process-ai --all --limit=100
   ```

6. **Queue worker** (required for jobs)
   ```bash
   php artisan queue:work
   ```

7. **Scheduler** (for incremental sync)
   ```bash
   php artisan schedule:work
   ```

## Commands

| Command | Description |
|---------|-------------|
| `zendesk:sync` | Incremental sync (tickets, users, orgs) |
| `zendesk:sync --full` | Full initial backfill |
| `zendesk:sync --tickets-only` | Sync tickets only |
| `zendesk:process-ai` | Run AI for tickets needing refresh |
| `zendesk:process-ai --all` | Reprocess all tickets (use after adding new AI fields) |

## Screens

- **Dashboard** - New/open/pending counts, AI high-severity queue
- **Tickets** - List with search, filters, **Pending** column (Us/Cust/Close)
- **Ticket Detail** - Full conversation, AI panel (incl. **Pending action** badge: Our side / Customer / Can close), similar tickets
- **Settings** - Zendesk connection test

## Architecture

- **Sync**: Cursor-based incremental ticket export, comments per ticket
- **AI**: OpenAI for classification (GPT-4o-mini) and embeddings (text-embedding-3-small)
- **Similarity**: pgvector cosine distance, duplicate boost for same requester + 7 days
- **PII**: CPF/CNPJ, email, phone redacted before AI prompts
