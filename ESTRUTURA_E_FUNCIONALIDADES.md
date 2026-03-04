# tecDESK — Estrutura e Funcionalidades

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
| `TicketOrder` | Ordem customizada dos tickets (por requester: `ticket_id`, `requester_id`, `sequence`) |

### Controllers

| Controller | Função |
|------------|--------|
| `AuthenticatedSessionController` | Login e logout |
| `DashboardController` | Dashboard com estatísticas (por org, requester, severidade, horas previstas, gráficos) |
| `TicketController` | Listagem, detalhe, refresh IA, tags, esforço interno, reordenação, attachments |

### Jobs (Queue)

| Job | Descrição |
|-----|-----------|
| `SyncZendeskTicketsJob` | Sync incremental de tickets (cursor), marca deletados e mesclados; atribui sequência por requester para novos tickets |
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
| `zendesk:process-ai` | Pipeline IA (resumo, classificação, esforço, embeddings) — 10 por vez |
| `zendesk:process-ai --no-limit` | Processa todos os pendentes de uma vez |
| `zendesk:process-ai --all` | Reprocessa todos os tickets |
| `zendesk:mark-deleted {zd_id}` | Marca ticket como deletado manualmente |
| `zendesk:sync-users-app` | Cria User para cada ZdUser (senha temporária) |
| `zendesk:sync-users-app --password=X` | Senha temporária (default: changeme) |
| `zendesk:sync-users-app --force` | Atualiza senha dos existentes para a temporária |
| `zendesk:test-connection` | Testa conexão Zendesk |
| `user:create-admin {email}` | Cria ou atualiza usuário admin |
| `user:create-admin {email} --password=X` | Define senha (ou será solicitada) |
| `user:set-password {email}` | Define/altera senha de um usuário (senha solicitada) |
| `user:set-password {email} --password=X` | Define senha via opção |

---

## Funcionalidades

### 0. Autenticação e Controle de Acesso

- **Login**: Email/senha, lembrar-me
- **Recuperação de senha**: Esqueci minha senha → email com link → redefinir senha (token expira em 60 min)
- **3 perfis**: admin, colaborador, cliente
- **Admin**: vê todos os tickets; criado via `AdminUserSeeder` ou `user:create-admin {email} [--password=X]`
- **Colaborador**: vê todos os tickets (igual ao admin)
- **Cliente**: vê apenas tickets onde é requester, submitter ou colaborador (cc)
- **Vínculo**: User.email = ZdUser.email para obter zd_id do Zendesk
- **Sync de usuários**: `zendesk:sync-users-app [--password=X]` cria um User para cada ZdUser (email ou zd_N@local.tecdesk se sem email); `user:set-password {email}` para alterar senha

### 1. Dashboard

- **Cards**: New, Open, Pending/Hold, Total ativos, Resolvidos (30d), Horas previstas
- **Admin**: Por organização (top 10), Por requester (top 10), Por severidade, Top categorias, Gráfico tickets por data (30d), Fila alta severidade
- **Colaborador**: Igual ao admin, exceto "Por requester"
- **Cliente**: Cards dos seus tickets, Horas previstas, Gráfico dos seus tickets por data, Meus tickets prioritários

### 2. Sync Zendesk

- **Incremental**: API cursor-based (`/incremental/tickets/cursor.json`)
- **Tickets deletados**: Marca `zd_deleted_at` e não exibe em tela
- **Tickets mesclados**: Oculta tickets com tag `closed_by_merged`
- **Filtros**: `ZENDESK_FILTER_REQUESTER_EMAILS`, `ZENDESK_EXCLUDE_STATUSES`
- **Scheduler**: Sync a cada 10 min; users/orgs a cada hora
- **Sem alteração**: Tickets com `zd_updated_at` igual não disparam FetchTicketCommentsJob nem `ai_needs_refresh`

### 3. Listagem de Tickets

- **Ordenação**: Por ID, Subject, Status, Priority, Created, Updated, Ordem
- **Ordem customizada**: Por usuário requisitante — agrupa por requester, sequência dentro de cada requester; drag-and-drop para priorizar (tabela `ticket_order`)
- **Novos tickets sincronizados**: Recebem automaticamente o próximo número da sequência do seu requester
- **Paginação**: 100 por página
- **Ordem padrão**: Por sequência (Ordem) ascendente
- **Filtros**: Search, Status, Priority, From/To, Organização (org)
- **Colunas**: Ordem, ID, Requester, Organização (admin/colaborador), Subject, Status, Priority, Category, Severity, Pending, Created, Updated, Age
- **Tooltip**: Resumo IA ao passar o mouse (hover 1s)

### 4. Seções

- **Tickets ativos**: new, open, pending, hold
- **Solved/Closed**: Tabela separada abaixo (exclui mesclados e deletados)

### 5. Detalhe do Ticket

- **Pessoas e organização** (admin/colaborador): Requester, Submitter, Assignee, Organização, Colaboradores (CC)
- **Cabeçalho**: ID, Subject, Status, Created (ZD), Updated (ZD), badge de idade (Fresh/Recent/Old/Too old)
- **AI Summary**: Bullets, perguntas abertas, ações necessárias, next_action, pending_action
- **Pendência**: Us (nossa vez), Cust (cliente), Close (pode fechar)
- **Previsões**: IA (read-only) e interna (editável)
- **Tickets similares**: Com score e média de resolução
- **Conversa**: Comentários com attachments (imagens proxy)

### 6. Ações

- **Atualizar IA**: Refresh de comentários + resumo (apenas admin)
- **Aplicar tags**: Tags sugeridas pela IA
- **Salvar previsão interna**: Esforço min/max em horas

### 7. IA (OpenAI)

- **Modelo**: GPT-4o-mini (configurável)
- **Embeddings**: text-embedding-3-small
- **Similaridade**: pgvector (cosine distance), boost para mesmo requester + 7 dias
- **Saída**: pt-BR
- **content_hash**: Hash SHA256 do conteúdo (subject + description + comments); evita reprocessar tickets sem alteração (SummarizeTicketJob e FetchTicketCommentsJob)

---

## Rotas

| Método | Rota | Descrição |
|--------|------|-----------|
| GET | `/login` | Formulário de login |
| POST | `/login` | Processar login |
| POST | `/logout` | Logout |
| GET | `/forgot-password` | Esqueci minha senha |
| POST | `/forgot-password` | Enviar link de recuperação |
| GET | `/reset-password/{token}` | Formulário de redefinição |
| POST | `/reset-password` | Salvar nova senha |
| GET | `/` | Dashboard (requer auth) |
| GET | `/tickets` | Lista de tickets |
| GET | `/tickets/{ticket}` | Detalhe do ticket |
| GET | `/tickets/{ticket}/attachments/{comment}/{index}` | Proxy de attachment |
| POST | `/tickets/{ticket}/refresh-ai` | Refresh IA (apenas admin) |
| POST | `/tickets/{ticket}/apply-tags` | Aplicar tags sugeridas |
| POST | `/tickets/{ticket}/internal-effort` | Salvar previsão interna |
| POST | `/tickets/reorder` | Reordenar tickets |

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

## Docker

- **Dockerfile**: PHP 8.2-fpm + nginx + supervisor (queue + scheduler)
- **docker-compose.yml**: app, postgres (pgvector/pgvector:pg17), redis
- **Entrypoint**: Migrations automáticas na primeira subida
- **Porta**: 8080 (app), 5432 (postgres), 6379 (redis)

## Requisitos

- PHP 8.2+
- PostgreSQL 17+ com extensão pgvector
- Redis
- Composer
