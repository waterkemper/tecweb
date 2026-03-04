<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ChangePasswordController extends Controller
{
    public function create(): View
    {
        return view('auth.change-password');
    }

    public function store(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => Hash::make($request->string('password')->toString()),
        ]);

        return redirect()->route('profile.password')
            ->with('status', 'Senha alterada com sucesso.');
    }
}
