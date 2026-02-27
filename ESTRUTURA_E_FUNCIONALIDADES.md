# Zendesk AI Support — Estrutura e Funcionalidades

Resumo da estrutura do projeto e das funcionalidades implementadas.

---

## Visão Geral

Aplicação interna Laravel que sincroniza tickets do Zendesk, aplica análise de IA (resumo, classificação, esforço, similaridade) e oferece interface para busca, filtros e ordenação.

---

## Estrutura do Projeto

### Diretórios Principais

```
Zendesk/
├── app/
│   ├── Console/Commands/     # Comandos Artisan
│   ├── Http/Controllers/      # Controllers
│   ├── Jobs/                 # Jobs assíncronos (queue)
│   ├── Models/               # Modelos Eloquent
│   └── Services/             # Serviços (Zendesk, redação)
├── config/                   # Configurações
├── database/migrations/      # Migrações
├── resources/views/          # Views Blade
└── routes/
    ├── web.php               # Rotas web
    └── console.php           # Scheduler
```

### Models

| Model | Descrição |
|-------|-----------|
| `User` | Usuário do app (admin, colaborador, cliente). Vinculado a ZdUser por email. |
| `ZdTicket` | Ticket do Zendesk (com scopes `not_deleted`, `not_merged`, `visibleToUser`) |
| `ZdTicketComment` | Comentários do ticket |
| `ZdUser` | Usuários Zendesk |
| `ZdOrg` | Organizações Zendesk |
| `ZdSyncState` | Estado do cursor de sync incremental |
| `AiTicketAnalysis` | Análise IA (resumo, classificação, esforço, pending_action) |
| `AiTicketAnalysisHistory` | Histórico de snapshots da análise |
| `AiTicketEmbedding` | Embeddings para similaridade (pgvector) |
| `AiSimilarTicket` | Relação de tickets similares |
| `AiFeedback` | Feedback sobre análise |
| `TicketOrder` | Ordem customizada dos tickets |

### Controllers

| Controller | Função |
|------------|--------|
| `AuthenticatedSessionController` | Login e logout |
| `DashboardController` | Dashboard com contagens (new/open/pending) e fila de alta severidade |
| `TicketController` | Listagem, detalhe, refresh IA, tags, esforço interno, reordenação, attachments |
| `SettingsController` | Configurações e teste de conexão Zendesk |

### Jobs (Queue)

| Job | Descrição |
|-----|-----------|
| `SyncZendeskTicketsJob` | Sync incremental de tickets (cursor), marca deletados e mesclados |
| `SyncZendeskUsersJob` | Sync de usuários |
| `SyncZendeskOrgsJob` | Sync de organizações |
| `FetchTicketCommentsJob` | Busca comentários de um ticket |
| `SummarizeTicketJob` | Resumo e extração (bullets, open_questions, actions, pending_action) |
| `ClassifyTicketJob` | Classificação (categorias, módulos, severidade, tags) |
| `EstimateEffortJob` | Estimativa de esforço em horas |
| `GenerateEmbeddingJob` | Gera embedding do ticket |
| `FindSimilarTicketsJob` | Encontra tickets similares via pgvector |

### Serviços

| Serviço | Descrição |
|---------|-----------|
| `ZendeskClient` | Cliente HTTP para API Zendesk (incremental, comments, ticket único) |
| `TicketRedactionService` | Redação de PII (CPF, CNPJ, email, telefone) antes de enviar à IA |

### Comandos Artisan

| Comando | Descrição |
|---------|-----------|
| `zendesk:sync` | Sync incremental (tickets, users, orgs) |
| `zendesk:sync --full` | Backfill inicial completo |
| `zendesk:sync --tickets-only` | Apenas tickets |
| `zendesk:sync --comments` | Busca comentários para tickets com ai_needs_refresh |
| `zendesk:process-ai` | Pipeline IA (resumo, classificação, esforço, embeddings) |
| `zendesk:process-ai --all` | Reprocessa todos os tickets |
| `zendesk:mark-deleted {zd_id}` | Marca ticket como deletado manualmente |
| `zendesk:sync-users-app` | Cria/atualiza usuários do app a partir do Zendesk (por email) |
| `zendesk:test-connection` | Testa conexão Zendesk |

---

## Funcionalidades

### 0. Autenticação e Controle de Acesso

- **3 perfis**: admin, colaborador, cliente
- **Admin**: vê todos os tickets; criado via `AdminUserSeeder` (ADMIN_EMAIL, ADMIN_PASSWORD)
- **Colaborador/Cliente**: vê apenas tickets onde é requester, submitter ou colaborador (cc)
- **Vínculo**: User.email = ZdUser.email para obter zd_id do Zendesk
- **Sync de usuários**: `zendesk:sync-users-app` cria/atualiza Users a partir de ZdUsers
- **Settings**: visível apenas para admin e colaborador

### 1. Sync Zendesk

- **Incremental**: API cursor-based (`/incremental/tickets/cursor.json`)
- **Tickets deletados**: Marca `zd_deleted_at` e não exibe em tela
- **Tickets mesclados**: Oculta tickets com tag `closed_by_merged`
- **Filtros**: `ZENDESK_FILTER_REQUESTER_EMAILS`, `ZENDESK_EXCLUDE_STATUSES`
- **Scheduler**: Sync a cada 10 min; users/orgs a cada hora

### 2. Listagem de Tickets

- **Ordenação**: Por ID, Subject, Status, Priority, Created, Updated, Ordem
- **Ordem customizada**: Drag-and-drop para priorizar (tabela `ticket_order`)
- **Paginação**: 100 por página
- **Ordem padrão**: Por sequência (Ordem) ascendente
- **Filtros**: Search, Status, Priority, From/To
- **Colunas**: Ordem, ID, Subject, Status, Priority, Category, Severity, Pending, Created, Updated, Age
- **Tooltip**: Resumo IA ao passar o mouse (hover 1s)

### 3. Seções

- **Tickets ativos**: new, open, pending, hold
- **Solved/Closed**: Tabela separada abaixo (exclui mesclados e deletados)

### 4. Detalhe do Ticket

- **Cabeçalho**: ID, Subject, Status, Created (ZD), Updated (ZD), badge de idade (Fresh/Recent/Old/Too old)
- **AI Summary**: Bullets, perguntas abertas, ações necessárias, next_action, pending_action
- **Pendência**: Us (nossa vez), Cust (cliente), Close (pode fechar)
- **Previsões**: IA (read-only) e interna (editável)
- **Tickets similares**: Com score e média de resolução
- **Conversa**: Comentários com attachments (imagens proxy)

### 5. Ações

- **Atualizar IA**: Refresh de comentários + resumo
- **Aplicar tags**: Tags sugeridas pela IA
- **Salvar previsão interna**: Esforço min/max em horas

### 6. IA (OpenAI)

- **Modelo**: GPT-4o-mini (configurável)
- **Embeddings**: text-embedding-3-small
- **Similaridade**: pgvector (cosine distance), boost para mesmo requester + 7 dias
- **Saída**: pt-BR

---

## Rotas

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/login` | Formulário de login |
| POST | `/login` | Processar login |
| POST | `/logout` | Logout |
| GET | `/` | Dashboard (requer auth) |
| GET | `/tickets` | Lista de tickets |
| GET | `/tickets/{ticket}` | Detalhe do ticket |
| GET | `/tickets/{ticket}/attachments/{comment}/{index}` | Proxy de attachment |
| POST | `/tickets/{ticket}/refresh-ai` | Refresh IA |
| POST | `/tickets/{ticket}/apply-tags` | Aplicar tags sugeridas |
| POST | `/tickets/{ticket}/internal-effort` | Salvar previsão interna |
| POST | `/tickets/reorder` | Reordenar tickets |
| GET | `/settings` | Configurações |
| POST | `/settings/test-zendesk` | Testar Zendesk |

---

## Configuração (.env)

| Variável | Descrição |
|----------|-----------|
| `ADMIN_EMAIL`, `ADMIN_PASSWORD` | Usuário admin inicial (AdminUserSeeder) |
| `SYNC_USER_DEFAULT_PASSWORD` | Senha padrão para usuários criados pelo sync (default: changeme) |
| `ZENDESK_SUBDOMAIN`, `ZENDESK_EMAIL`, `ZENDESK_API_TOKEN` | API Zendesk |
| `ZENDESK_FILTER_REQUESTER_EMAILS` | Filtro por emails (opcional) |
| `ZENDESK_EXCLUDE_STATUSES` | Status excluídos do sync (default: closed) |
| `ZENDESK_PRIORITY_ORDER` | Ordem de prioridade (urgent,high,normal,low) |
| `ZENDESK_TICKET_AGE_OLD_DAYS`, `ZENDESK_TICKET_AGE_TOO_OLD_DAYS` | Dias para badge Old/Too old |
| `OPENAI_API_KEY` | API OpenAI |
| `DB_*` | PostgreSQL |
| `REDIS_*`, `QUEUE_CONNECTION=redis` | Queue e cache |

---

## Requisitos

- PHP 8.2+
- PostgreSQL 16+ com extensão pgvector
- Redis
- Composer
