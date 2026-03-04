<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ZdOrg;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function index(): View
    {
        $orgs = ZdOrg::query()
            ->withCount(['tickets', 'zdUsers'])
            ->orderBy('name')
            ->paginate(50);

        return view('admin.organizations.index', ['organizations' => $orgs]);
    }

    public function users(ZdOrg $organization): View
    {
        $zdUsers = $organization->zdUsers()
            ->orderBy('name')
            ->paginate(50, ['*'], 'page')
            ->withQueryString();

        $appUsersByZdUserId = User::whereIn('zd_user_id', $zdUsers->pluck('id'))
            ->get()
            ->keyBy('zd_user_id');

        return view('admin.organizations.users', [
            'zdUsers' => $zdUsers,
            'organization' => $organization,
            'appUsersByZdUserId' => $appUsersByZdUserId,
        ]);
    }
}
