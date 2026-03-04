<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'tecDESK') - {{ config('app.name') }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2/dist/tailwind.min.css" rel="stylesheet">
    @endif
    <style>
        body { font-family: system-ui, sans-serif; }
        .container { max-width: 1280px; margin: 0 auto; padding: 1rem 2rem; }
        nav { display: flex; gap: 1.5rem; padding: 1rem 0; border-bottom: 1px solid #e5e7eb; }
        nav a { color: #374151; text-decoration: none; }
        nav a:hover { color: #111827; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; }
        .badge { display: inline-block; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; }
        .badge-new { background: #dbeafe; color: #1e40af; }
        .badge-open { background: #fef3c7; color: #92400e; }
        .badge-pending { background: #e5e7eb; color: #6b7280; }
        .badge-hold { background: #e0e7ff; color: #3730a3; }
        .badge-solved { background: #d1fae5; color: #065f46; }
        .badge-closed { background: #f3f4f6; color: #4b5563; }
        .badge-critical { background: #fee2e2; color: #991b1b; }
        .badge-high { background: #ffedd5; color: #9a3412; }
        .badge-medium { background: #fef3c7; color: #92400e; }
        .badge-low { background: #d1fae5; color: #065f46; }
        .card { background: white; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; }
        .stat-card { background: #f9fafb; padding: 1rem; border-radius: 0.5rem; }
        .stat-value { font-size: 1.5rem; font-weight: 700; }
        input, select { padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.25rem; }
    </style>
</head>
<body class="bg-gray-50">
    <div id="ai-preview-tooltip" style="display:none;position:fixed;z-index:99999;max-width:380px;padding:1rem;background:#fff;border-radius:0.5rem;box-shadow:0 4px 20px rgba(0,0,0,0.25);border:2px solid #3b82f6;font-size:0.875rem;text-align:left;pointer-events:none;"></div>
    <div class="container">
        <nav class="flex items-center justify-between">
            <div class="flex items-center gap-8">
                <a href="{{ route('dashboard') }}" class="font-semibold text-slate-800">tecDESK</a>
                <a href="{{ route('dashboard') }}" class="text-slate-600 hover:text-slate-900">Dashboard</a>
                <a href="{{ route('tickets.index') }}" class="text-slate-600 hover:text-slate-900">Tickets</a>
                @if (auth()->user()?->isAdmin())
                    <a href="{{ route('admin.organizations.index') }}" class="text-slate-600 hover:text-slate-900">Admin</a>
                @endif
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm text-gray-600">{{ auth()->user()?->name }} ({{ auth()->user()?->role }})</span>
                <a href="{{ route('profile.password') }}" class="text-sm text-gray-600 hover:text-gray-900">Alterar senha</a>
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="text-sm text-gray-600 hover:text-gray-900">Sair</button>
                </form>
            </div>
        </nav>
        <main class="py-6">
            @yield('content')
        </main>
    </div>
</body>
</html>
