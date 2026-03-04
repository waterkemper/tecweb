<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->with(['zdUser.organization'])
            ->orderBy('name')
            ->paginate(50);

        return view('admin.users.index', ['users' => $users]);
    }

    public function edit(User $user): View
    {
        $user->load('zdUser.organization');

        return view('admin.users.edit', ['user' => $user]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'can_view_org_tickets' => 'boolean',
        ]);

        $user->update([
            'can_view_org_tickets' => $request->boolean('can_view_org_tickets'),
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Usuário atualizado com sucesso.');
    }
}
