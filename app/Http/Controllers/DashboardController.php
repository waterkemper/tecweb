<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ZdTicket;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $baseQuery = ZdTicket::visibleToUser($user);

        $newCount = (clone $baseQuery)->where('status', 'new')->count();
        $openCount = (clone $baseQuery)->where('status', 'open')->count();
        $pendingCount = (clone $baseQuery)->where('status', 'pending')->count();
        $waitingCount = (clone $baseQuery)->whereIn('status', ['pending', 'hold'])->count();
        $highSeverity = (clone $baseQuery)->with('analysis')
            ->whereHas('analysis', fn ($q) => $q->whereIn('severity', ['critical', 'high']))
            ->whereIn('status', ['new', 'open'])
            ->limit(10)
            ->get();

        return view('dashboard', [
            'newCount' => $newCount,
            'openCount' => $openCount,
            'pendingCount' => $pendingCount,
            'waitingCount' => $waitingCount,
            'highSeverityTickets' => $highSeverity,
        ]);
    }
}
