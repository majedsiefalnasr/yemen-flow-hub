<?php

namespace App\Http\Controllers;

use App\Models\User;

class TestApiController extends Controller
{
    public function index()
    {
        $users = User::with('bank')
            ->where('is_active', true)
            ->orderBy('role')
            ->orderBy('bank_id')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->legacyRole()?->value,
                'role_label' => $u->role->label(),
                'bank_name' => $u->bank?->name,
                'bank_code' => $u->bank?->code,
            ]);

        return view('test_api', compact('users'));
    }
}
