<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ZendeskClient;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        return view('settings.index', [
            'zendeskConfigured' => (bool) config('zendesk.subdomain') && config('zendesk.api_token'),
        ]);
    }

    public function testZendesk(): \Illuminate\Http\JsonResponse
    {
        try {
            $client = app(ZendeskClient::class);
            $ok = $client->testConnection();
            return response()->json(['success' => $ok, 'message' => $ok ? 'Connection OK' : 'Connection failed']);
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            if ($e instanceof \Illuminate\Http\Client\RequestException && $e->response) {
                $body = $e->response->json();
                $message = $body['error'] ?? $body['message'] ?? $e->response->body() ?: $message;
            }
            return response()->json(['success' => false, 'message' => $message], 500);
        }
    }
}
