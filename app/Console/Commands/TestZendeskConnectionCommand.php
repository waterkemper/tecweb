<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ZendeskClient;
use Illuminate\Console\Command;

class TestZendeskConnectionCommand extends Command
{
    protected $signature = 'zendesk:test';

    protected $description = 'Test Zendesk API connection';

    public function handle(): int
    {
        $this->info('Testing Zendesk connection...');
        $this->info('Subdomain: ' . (config('zendesk.subdomain') ?: '(not set)'));
        $this->info('Email: ' . (config('zendesk.email') ?: '(not set)'));

        try {
            $client = app(ZendeskClient::class);
            $client->testConnection();
            $this->info('Connection OK!');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Connection failed: ' . $e->getMessage());
            if ($e instanceof \Illuminate\Http\Client\RequestException && $e->response) {
                $this->line('Status: ' . $e->response->status());
                $this->line('Body: ' . $e->response->body());
            }
            return self::FAILURE;
        }
    }
}
