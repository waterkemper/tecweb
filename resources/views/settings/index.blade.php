@extends('layouts.app')

@section('title', 'Settings')

@section('content')
<h1 class="text-2xl font-bold mb-6">Settings</h1>

<div class="card max-w-2xl">
    <h2 class="font-semibold mb-4">Zendesk Connection</h2>
    <p class="text-sm text-gray-600 mb-4">
        Configure in <code>.env</code>: ZENDESK_SUBDOMAIN, ZENDESK_EMAIL, ZENDESK_API_TOKEN
    </p>
    <p class="text-sm mb-2">
        Status: @if ($zendeskConfigured)
            <span class="text-green-600">Configured</span>
        @else
            <span class="text-amber-600">Not configured</span>
        @endif
    </p>
    <button type="button" onclick="testConnection()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">
        Test Connection
    </button>
    <p id="test-result" class="mt-2 text-sm hidden"></p>
</div>

<div class="card max-w-2xl mt-6">
    <h2 class="font-semibold mb-4">Sync Commands</h2>
    <pre class="text-sm bg-gray-100 p-4 rounded overflow-auto">
# Full initial backfill
php artisan zendesk:sync --full

# Incremental sync (default)
php artisan zendesk:sync

# Process AI for tickets needing refresh
php artisan zendesk:process-ai --limit=20
    </pre>
</div>

<script>
function testConnection() {
    const el = document.getElementById('test-result');
    el.classList.remove('hidden');
    el.textContent = 'Testing...';
    fetch('{{ route("settings.test") }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(d => {
        el.textContent = d.success ? 'OK: ' + d.message : 'Failed: ' + d.message;
        el.className = 'mt-2 text-sm ' + (d.success ? 'text-green-600' : 'text-red-600');
    })
    .catch(e => {
        el.textContent = 'Error: ' + e.message;
        el.className = 'mt-2 text-sm text-red-600';
    });
}
</script>
@endsection
