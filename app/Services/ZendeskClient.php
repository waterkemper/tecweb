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
        return [
            'users' => $data['users'] ?? [],
            'next_page' => $data['links']['next'] ?? null,
            'end_of_stream' => ! ($data['meta']['has_more'] ?? false),
        ];
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
