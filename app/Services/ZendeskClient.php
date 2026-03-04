<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZendeskClient
{
    private string $baseUrl;

    private int $rateLimitPerMinute;

    private int $requestCount = 0;

    private float $minuteStart;

    public function __construct()
    {
        $subdomain = config('zendesk.subdomain');
        $this->baseUrl = "https://{$subdomain}.zendesk.com/api/v2";
        $this->rateLimitPerMinute = config('zendesk.rate_limit_per_minute', 400);
        $this->minuteStart = microtime(true);
    }

    protected function client(): PendingRequest
    {
        $client = Http::withBasicAuth(
            config('zendesk.email') . '/token',
            config('zendesk.api_token')
        )
            ->acceptJson()
            ->contentType('application/json')
            ->timeout(60);

        if (! config('zendesk.ssl_verify', true)) {
            $client->withOptions(['verify' => false]);
        }

        return $client;
    }

    /**
     * Throttle requests to respect Zendesk rate limits.
     */
    protected function throttle(): void
    {
        $this->requestCount++;
        $elapsed = microtime(true) - $this->minuteStart;
        if ($elapsed >= 60) {
            $this->requestCount = 1;
            $this->minuteStart = microtime(true);
        } elseif ($this->requestCount >= $this->rateLimitPerMinute) {
            $sleep = (int) ceil(60 - $elapsed);
            if ($sleep > 0) {
                Log::info("Zendesk rate limit: sleeping {$sleep}s");
                sleep($sleep);
            }
            $this->requestCount = 0;
            $this->minuteStart = microtime(true);
        }
    }

    /**
     * Execute request with retry on 429.
     */
    protected function request(string $method, string $url, array $data = []): array
    {
        $this->throttle();

        $maxRetries = 5;
        $attempt = 0;

        while ($attempt < $maxRetries) {
            $response = $this->client()->$method($url, $data);

            if ($response->status() === 429) {
                $retryAfter = (int) $response->header('Retry-After', 60);
                Log::warning("Zendesk 429: waiting {$retryAfter}s");
                sleep($retryAfter);
                $attempt++;
                continue;
            }

            $response->throw();
            return $response->json();
        }

        throw new \RuntimeException('Zendesk API rate limit exceeded after retries');
    }

    /**
     * Cursor-based incremental ticket export.
     * Requires start_time (unix) or cursor; start_time must be at least 1 minute in the past.
     */
    public function getIncrementalTickets(?string $cursor = null, ?int $startTime = null): array
    {
        $params = [];
        if ($cursor) {
            $params['cursor'] = $cursor;
        } elseif ($startTime !== null && $startTime > 0) {
            $params['start_time'] = $startTime;
        }

        if (empty($params)) {
            $params['start_time'] = time() - 86400; // fallback: 1 day ago
        }

        $url = $this->baseUrl . '/incremental/tickets/cursor.json';
        return $this->request('get', $url, $params);
    }

    /**
     * Get ticket comments.
     */
    public function getTicketComments(int $ticketId): array
    {
        $url = $this->baseUrl . "/tickets/{$ticketId}/comments.json";
        $all = [];
        $page = null;

        do {
            $reqUrl = $page ?? $url;
            $data = $this->request('get', $reqUrl);
            $all = array_merge($all, $data['comments'] ?? []);
            $page = $data['next_page'] ?? null;
        } while ($page);

        return $all;
    }

    /**
     * Search tickets by requester (email or Zendesk user ID) via Search API.
     * Yields full ticket objects (fetched via getTicket for complete data).
     * Max 1000 results per query (Search API limit).
     *
     * @param string|int $emailOrId Requester email or Zendesk user ID
     * @return \Generator<array> Full ticket objects
     */
    public function searchTicketsByRequester(string|int $emailOrId): \Generator
    {
        $value = is_int($emailOrId) ? (string) $emailOrId : trim((string) $emailOrId);
        if ($value === '') {
            return;
        }
        $requesterTerm = is_int($emailOrId) ? $value : $value;
        $query = 'type:ticket requester:' . $requesterTerm;
        $url = $this->baseUrl . '/search.json';
        $page = null;
        $maxPages = 10;

        for ($pages = 0; $pages < $maxPages; $pages++) {
            if ($page !== null) {
                $reqUrl = $page;
                $data = $this->request('get', $reqUrl);
            } else {
                $data = $this->request('get', $url, [
                    'query' => $query,
                    'sort_by' => 'updated_at',
                    'sort_order' => 'desc',
                ]);
            }
            $results = $data['results'] ?? [];
            foreach ($results as $r) {
                if (($r['result_type'] ?? '') !== 'ticket') {
                    continue;
                }
                $ticketId = $r['id'] ?? null;
                if ($ticketId === null) {
                    continue;
                }
                try {
                    $full = $this->getTicket((int) $ticketId);
                    if ($full !== null) {
                        yield $full;
                    }
                } catch (\Throwable $e) {
                    Log::warning('ZendeskClient: falha ao buscar ticket', ['id' => $ticketId, 'error' => $e->getMessage()]);
                }
            }
            $page = $data['next_page'] ?? null;
            if ($page === null || count($results) < 100) {
                break;
            }
        }
    }

    /**
     * Get single ticket.
     */
    public function getTicket(int $ticketId): ?array
    {
        $url = $this->baseUrl . "/tickets/{$ticketId}.json";
        try {
            $data = $this->request('get', $url);
            return $data['ticket'] ?? null;
        } catch (RequestException $e) {
            if ($e->response?->status() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Incremental user export (time-based).
     * Requires admin/agent permissions; may return 403 on some plans.
     */
    public function getIncrementalUsers(int $startTime): array
    {
        $url = $this->baseUrl . '/incremental/users.json?start_time=' . $startTime;
        return $this->request('get', $url);
    }

    /**
     * List users via cursor pagination (fallback when incremental export returns 403).
     * Returns users in pages; compatible with SyncZendeskUsersJob processing.
     */
    public function getUsersListPage(?string $nextUrl = null): array
    {
        $url = $nextUrl ?? $this->baseUrl . '/users.json?page[size]=100';

        $data = $this->request('get', $url);
        $hasMore = $data['meta']['has_more'] ?? false;
        $nextCursor = $data['meta']['after_cursor'] ?? null;
        $nextPage = $data['links']['next'] ?? $data['next_page'] ?? null;

        if ($hasMore && $nextPage === null && $nextCursor !== null) {
            $nextPage = $this->baseUrl . '/users.json?page[size]=100&page[after]=' . rawurlencode($nextCursor);
        }

        if ($hasMore && $nextPage === null) {
            Log::warning('ZendeskClient: has_more=true mas next_page ausente', [
                'links' => $data['links'] ?? null,
                'meta' => $data['meta'] ?? null,
            ]);
        }

        return [
            'users' => $data['users'] ?? [],
            'next_page' => $nextPage,
            'end_of_stream' => ! $hasMore,
        ];
    }

    /**
     * Search users by email. Tries org members first (when domain matches), then General Search, then Users Search.
     */
    public function searchUsersByEmail(string $email): array
    {
        $emailLower = strtolower(trim($email));
        $domain = substr(strrchr($email, '@'), 1);
        if ($domain) {
            $users = $this->searchUsersByOrganizationDomain($domain, $emailLower);
            if (! empty($users)) {
                return $users;
            }
        }

        $url = $this->baseUrl . '/search.json?query=' . rawurlencode('type:user email:' . $email);
        try {
            $all = [];
            $page = null;
            $maxPages = 10;
            $pages = 0;
            do {
                $reqUrl = $page ?? $url;
                $data = $this->request('get', $reqUrl);
                $results = $data['results'] ?? [];
                foreach ($results as $r) {
                    if (($r['result_type'] ?? '') === 'user') {
                        $all[] = $r;
                    }
                }
                foreach ($all as $u) {
                    $e = $u['email'] ?? null;
                    if ($e !== null && strtolower(trim($e)) === $emailLower) {
                        $full = $this->getUserById($u['id'] ?? 0);
                        return $full ? [$full] : [$u];
                    }
                }
                $page = $data['next_page'] ?? null;
                $pages++;
            } while ($page && $pages < $maxPages);
        } catch (\Throwable $e) {
            Log::debug('ZendeskClient: search type:user falhou', ['error' => $e->getMessage()]);
        }

        $url = $this->baseUrl . '/users/search.json?query=' . rawurlencode($email);
        try {
            $all = [];
            $nextUrl = null;
            $maxPages = 15;
            $pages = 0;
            do {
                $reqUrl = $nextUrl ?? $url;
                $data = $this->request('get', $reqUrl);
                $users = $data['users'] ?? [];
                $all = array_merge($all, $users);
                foreach ($all as $u) {
                    $e = $u['email'] ?? null;
                    if ($e !== null && strtolower(trim($e)) === $emailLower) {
                        $full = $this->getUserById($u['id'] ?? 0);
                        return $full ? [$full] : [$u];
                    }
                }
                $nextUrl = $data['next_page'] ?? $data['links']['next'] ?? null;
                $pages++;
            } while ($nextUrl && count($users) >= 100 && $pages < $maxPages);
        } catch (\Throwable $e) {
            Log::debug('ZendeskClient: users/search falhou', ['error' => $e->getMessage()]);
        }

        return [];
    }

    /**
     * Get users from organizations matching domain, filter by exact email.
     */
    private function searchUsersByOrganizationDomain(string $domain, string $emailLower): array
    {
        $orgs = \App\Models\ZdOrg::where(function ($q) use ($domain) {
            $q->whereRaw('LOWER(domain_names::text) LIKE ?', ['%' . strtolower($domain) . '%'])
                ->orWhereRaw('LOWER(name) LIKE ?', ['%' . str_replace('.com.br', '', $domain) . '%']);
        })->limit(5)->get();

        foreach ($orgs as $org) {
            try {
                $url = $this->baseUrl . '/organizations/' . $org->zd_id . '/organization_memberships.json?include=users&page[size]=100';
                $all = [];
                $nextUrl = null;
                do {
                    $reqUrl = $nextUrl ?? $url;
                    $data = $this->request('get', $reqUrl);
                    $memberships = $data['organization_memberships'] ?? [];
                    $users = $data['users'] ?? [];
                    foreach ($users as $u) {
                        $e = $u['email'] ?? null;
                        if ($e !== null && strtolower(trim($e)) === $emailLower) {
                            $full = $this->getUserById($u['id'] ?? 0);
                            return $full ? [$full] : [$u];
                        }
                    }
                    $nextUrl = $data['links']['next'] ?? $data['meta']['after_cursor'] ?? null;
                    if ($nextUrl && ! str_starts_with((string) $nextUrl, 'http')) {
                        $nextUrl = $this->baseUrl . '/organizations/' . $org->zd_id . '/organization_memberships.json?include=users&page[size]=100&page[after]=' . rawurlencode($nextUrl);
                    }
                } while ($nextUrl);
            } catch (\Throwable $e) {
                Log::debug('ZendeskClient: org members falhou', ['org' => $org->zd_id, 'error' => $e->getMessage()]);
            }
        }

        return [];
    }

    /**
     * Get full user object by ID (for processUsers compatibility).
     */
    public function getUserById(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }
        try {
            $data = $this->request('get', $this->baseUrl . "/users/{$userId}.json");
            return $data['user'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get organization by ID (for on-the-fly creation when processing tickets).
     */
    public function getOrganizationById(int $orgId): ?array
    {
        if ($orgId <= 0) {
            return null;
        }
        try {
            $data = $this->request('get', $this->baseUrl . "/organizations/{$orgId}.json");
            return $data['organization'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Incremental organization export (time-based).
     * May return 400/403 on some plans; use getOrganizationsListPage as fallback.
     */
    public function getIncrementalOrganizations(int $startTime): array
    {
        $url = $this->baseUrl . '/incremental/organizations.json?start_time=' . $startTime;
        return $this->request('get', $url);
    }

    /**
     * List organizations via cursor pagination (fallback when incremental returns 400/403).
     */
    public function getOrganizationsListPage(?string $nextUrl = null): array
    {
        $url = $nextUrl ?? $this->baseUrl . '/organizations.json?page[size]=100';
        $data = $this->request('get', $url);
        return [
            'organizations' => $data['organizations'] ?? [],
            'next_page' => $data['links']['next'] ?? null,
            'end_of_stream' => ! ($data['meta']['has_more'] ?? false),
        ];
    }

    /**
     * Incremental ticket events (for status/assignee/tag changes).
     */
    public function getIncrementalTicketEvents(int $startTime): array
    {
        $url = $this->baseUrl . '/incremental/ticket_events.json?start_time=' . $startTime;
        return $this->request('get', $url);
    }

    /**
     * Test connection. Returns true on success, throws on failure.
     */
    public function testConnection(): bool
    {
        $url = $this->baseUrl . '/users/me.json';
        $this->request('get', $url);
        return true;
    }

    /**
     * Upload a file to Zendesk. Returns the upload token for attaching to a comment.
     * Max 50 MB per file.
     */
    public function uploadFile(string $filename, string $content, string $contentType): ?string
    {
        $this->throttle();

        $url = $this->baseUrl . '/uploads.json?filename=' . rawurlencode($filename);

        $client = Http::withBasicAuth(
            config('zendesk.email') . '/token',
            config('zendesk.api_token')
        )
            ->acceptJson()
            ->withBody($content, $contentType)
            ->timeout(120);

        if (! config('zendesk.ssl_verify', true)) {
            $client = $client->withOptions(['verify' => false]);
        }

        $response = $client->post($url);

        if (! $response->successful()) {
            Log::warning('ZendeskClient: upload failed', [
                'filename' => $filename,
                'status' => $response->status(),
            ]);
            return null;
        }

        $data = $response->json();
        return $data['upload']['token'] ?? null;
    }

    /**
     * Add a comment to a ticket. Optionally include upload tokens from uploadFile().
     */
    public function addTicketComment(int $zdTicketId, string $body, bool $public = true, array $uploadTokens = [], ?int $authorId = null): void
    {
        $comment = [
            'body' => $body,
            'public' => $public,
        ];
        if (! empty($uploadTokens)) {
            $comment['uploads'] = $uploadTokens;
        }
        if ($authorId !== null) {
            $comment['author_id'] = $authorId;
        }

        $this->updateTicket($zdTicketId, ['comment' => $comment]);
    }

    /**
     * Update ticket fields (status, comment, assignee_id, etc.).
     */
    public function updateTicket(int $zdTicketId, array $data): void
    {
        $url = $this->baseUrl . "/tickets/{$zdTicketId}.json";
        $this->request('put', $url, ['ticket' => $data]);
    }

    /**
     * Fetch attachment file from Zendesk content_url (for proxy).
     * Only fetches URLs on zendesk.com domain. Returns null for external URLs.
     */
    public function fetchAttachment(string $contentUrl): ?\Illuminate\Http\Client\Response
    {
        $parsed = parse_url($contentUrl);
        $host = $parsed['host'] ?? '';
        if (! str_contains($host, 'zendesk.com')) {
            return null;
        }

        $client = Http::withBasicAuth(
            config('zendesk.email') . '/token',
            config('zendesk.api_token')
        )->timeout(30);

        if (! config('zendesk.ssl_verify', true)) {
            $client = $client->withOptions(['verify' => false]);
        }

        $response = $client->get($contentUrl);
        if (! $response->successful()) {
            return null;
        }

        return $response;
    }
}
