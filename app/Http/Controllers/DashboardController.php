<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AiTicketAnalysis;
use App\Models\ZdOrg;
use App\Models\ZdTicket;
use App\Models\ZdUser;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const ACTIVE_STATUSES = ['new', 'open', 'pending', 'hold'];

    public function index(): View
    {
        $user = auth()->user();
        $baseQuery = ZdTicket::visibleToUser($user);
        $activeQuery = (clone $baseQuery)->whereIn('status', self::ACTIVE_STATUSES);

        $newCount = (clone $baseQuery)->where('status', 'new')->count();
        $openCount = (clone $baseQuery)->where('status', 'open')->count();
        $pendingCount = (clone $baseQuery)->whereIn('status', ['pending', 'hold'])->count();
        $totalActive = $activeQuery->count();
        $resolvedLast30 = (clone $baseQuery)
            ->whereIn('status', ['solved', 'closed'])
            ->where('zd_updated_at', '>=', now()->subDays(30))
            ->count();

        $hoursPredicted = $this->computeHoursPredicted($user);

        $highSeverity = (clone $baseQuery)->with(['analysis' => fn ($q) => $q->latest()->limit(1)])
            ->whereHas('analysis', fn ($q) => $q->whereIn('severity', ['critical', 'high']))
            ->whereIn('status', ['new', 'open'])
            ->limit(10)
            ->get();

        $byOrg = null;
        $byRequester = null;
        $bySeverity = [];
        $byCategory = [];
        $ticketsByDate = [];

        if (in_array($user->role, ['admin', 'colaborador']) || $user->canViewAllOrgTickets()) {
            $byOrg = $this->ticketsByOrganization($user);
            $bySeverity = $this->ticketsBySeverity($user);
            $byCategory = $this->ticketsByCategory($user);
            $ticketsByDate = $this->ticketsByDate($user, 30);

            if ($user->role === 'admin') {
                $byRequester = $this->ticketsByRequester($user);
            }
        } elseif ($user->role === 'cliente') {
            $ticketsByDate = $this->ticketsByDate($user, 30);
        }

        return view('dashboard', [
            'newCount' => $newCount,
            'openCount' => $openCount,
            'pendingCount' => $pendingCount,
            'totalActive' => $totalActive,
            'resolvedLast30' => $resolvedLast30,
            'hoursPredicted' => $hoursPredicted,
            'highSeverityTickets' => $highSeverity,
            'byOrg' => $byOrg,
            'byRequester' => $byRequester,
            'bySeverity' => $bySeverity,
            'byCategory' => $byCategory,
            'ticketsByDate' => $ticketsByDate,
        ]);
    }

    private function computeHoursPredicted($user): float
    {
        $ticketIds = ZdTicket::visibleToUser($user)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->pluck('id');

        if ($ticketIds->isEmpty()) {
            return 0.0;
        }

        $latestIds = DB::table('ai_ticket_analysis')
            ->whereIn('ticket_id', $ticketIds)
            ->selectRaw('ticket_id, MAX(id) as id')
            ->groupBy('ticket_id')
            ->pluck('id');

        return (float) DB::table('ai_ticket_analysis')
            ->whereIn('id', $latestIds)
            ->get()
            ->sum(fn ($a) => (float) ($a->internal_effort_max ?? $a->internal_effort_min ?? $a->effort_max ?? $a->effort_min ?? 0));
    }

    private function ticketsByOrganization($user): \Illuminate\Support\Collection
    {
        $activeIds = ZdTicket::visibleToUser($user)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->whereNotNull('org_id')
            ->pluck('id');

        if ($activeIds->isEmpty()) {
            return collect();
        }

        $counts = ZdTicket::visibleToUser($user)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->whereNotNull('org_id')
            ->selectRaw('org_id, count(*) as cnt')
            ->groupBy('org_id')
            ->orderByDesc('cnt')
            ->limit(10)
            ->pluck('cnt', 'org_id');

        $orgIds = $counts->keys()->map(fn ($k) => (int) $k)->all();
        $orgs = ZdOrg::whereIn('zd_id', $orgIds)->get()->keyBy('zd_id');

        $hoursByOrg = $this->hoursByOrg($user, $orgIds);

        return $counts->map(fn ($cnt, $orgId) => [
            'org' => $orgs->get($orgId),
            'count' => $cnt,
            'hours' => $hoursByOrg[$orgId] ?? 0,
        ])->values();
    }

    private function hoursByOrg($user, array $orgIds): array
    {
        if (empty($orgIds)) {
            return [];
        }

        $ticketIds = ZdTicket::visibleToUser($user)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->whereIn('org_id', $orgIds)
            ->pluck('id');

        if ($ticketIds->isEmpty()) {
            return array_fill_keys($orgIds, 0);
        }

        $ticketToOrg = ZdTicket::whereIn('id', $ticketIds)->pluck('org_id', 'id');
        $latestIds = DB::table('ai_ticket_analysis')
            ->whereIn('ticket_id', $ticketIds)
            ->selectRaw('ticket_id, MAX(id) as id')
            ->groupBy('ticket_id')
            ->get();

        $hoursByTicket = DB::table('ai_ticket_analysis')
            ->whereIn('id', $latestIds->pluck('id'))
            ->get()
            ->keyBy('id');

        $result = array_fill_keys($orgIds, 0.0);
        foreach ($latestIds as $row) {
            $a = $hoursByTicket->get($row->id);
            $h = (float) ($a->internal_effort_max ?? $a->internal_effort_min ?? $a->effort_max ?? $a->effort_min ?? 0);
            $orgId = $ticketToOrg->get($row->ticket_id);
            if ($orgId !== null) {
                $result[$orgId] = ($result[$orgId] ?? 0) + $h;
            }
        }

        return $result;
    }

    private function ticketsByRequester($user): \Illuminate\Support\Collection
    {
        $counts = ZdTicket::visibleToUser($user)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->whereNotNull('requester_id')
            ->selectRaw('requester_id, count(*) as cnt')
            ->groupBy('requester_id')
            ->orderByDesc('cnt')
            ->limit(10)
            ->pluck('cnt', 'requester_id');

        $requesterIds = $counts->keys()->map(fn ($k) => (int) $k)->all();
        $users = ZdUser::whereIn('zd_id', $requesterIds)->get()->keyBy('zd_id');

        $hoursByRequester = $this->hoursByRequester($user, $requesterIds);

        return $counts->map(fn ($cnt, $reqId) => [
            'user' => $users->get($reqId),
            'count' => $cnt,
            'hours' => $hoursByRequester[$reqId] ?? 0,
        ])->values();
    }

    private function hoursByRequester($user, array $requesterIds): array
    {
        if (empty($requesterIds)) {
            return [];
        }

        $ticketIds = ZdTicket::visibleToUser($user)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->whereIn('requester_id', $requesterIds)
            ->pluck('id');

        if ($ticketIds->isEmpty()) {
            return array_fill_keys($requesterIds, 0);
        }

        $ticketToReq = ZdTicket::whereIn('id', $ticketIds)->pluck('requester_id', 'id');
        $latestIds = DB::table('ai_ticket_analysis')
            ->whereIn('ticket_id', $ticketIds)
            ->selectRaw('ticket_id, MAX(id) as id')
            ->groupBy('ticket_id')
            ->get();

        $hoursByTicket = DB::table('ai_ticket_analysis')
            ->whereIn('id', $latestIds->pluck('id'))
            ->get()
            ->keyBy('id');

        $result = array_fill_keys($requesterIds, 0.0);
        foreach ($latestIds as $row) {
            $a = $hoursByTicket->get($row->id);
            $h = (float) ($a->internal_effort_max ?? $a->internal_effort_min ?? $a->effort_max ?? $a->effort_min ?? 0);
            $reqId = $ticketToReq->get($row->ticket_id);
            if ($reqId !== null) {
                $result[$reqId] = ($result[$reqId] ?? 0) + $h;
            }
        }

        return $result;
    }

    private function ticketsBySeverity($user): array
    {
        $ticketIds = ZdTicket::visibleToUser($user)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->pluck('id');

        if ($ticketIds->isEmpty()) {
            return ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
        }

        $latestIds = DB::table('ai_ticket_analysis')
            ->whereIn('ticket_id', $ticketIds)
            ->selectRaw('ticket_id, MAX(id) as id')
            ->groupBy('ticket_id')
            ->pluck('id');

        $severities = DB::table('ai_ticket_analysis')
            ->whereIn('id', $latestIds)
            ->pluck('severity');

        $result = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
        foreach ($severities as $s) {
            $key = in_array($s, array_keys($result)) ? $s : 'low';
            $result[$key] = ($result[$key] ?? 0) + 1;
        }

        return $result;
    }

    private function ticketsByCategory($user): array
    {
        $ticketIds = ZdTicket::visibleToUser($user)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->pluck('id');

        if ($ticketIds->isEmpty()) {
            return [];
        }

        $latestIds = DB::table('ai_ticket_analysis')
            ->whereIn('ticket_id', $ticketIds)
            ->selectRaw('ticket_id, MAX(id) as id')
            ->groupBy('ticket_id')
            ->pluck('id');

        $categories = DB::table('ai_ticket_analysis')
            ->whereIn('id', $latestIds)
            ->pluck('category')
            ->map(fn ($c) => $c ?: 'outro');

        return $categories->countBy()->sortDesc()->take(5)->all();
    }

    private function ticketsByDate($user, int $days): array
    {
        $dates = collect();
        for ($i = $days - 1; $i >= 0; $i--) {
            $dates->put(now()->subDays($i)->format('Y-m-d'), 0);
        }

        $counts = ZdTicket::visibleToUser($user)
            ->selectRaw('(zd_created_at::date)::text as d, count(*) as cnt')
            ->where('zd_created_at', '>=', now()->subDays($days))
            ->groupByRaw('zd_created_at::date')
            ->pluck('cnt', 'd');

        foreach ($counts as $d => $cnt) {
            $dates->put($d, $cnt);
        }

        return $dates->all();
    }
}
