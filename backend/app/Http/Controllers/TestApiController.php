<?php

namespace App\Http\Controllers;

use App\Models\User;

class TestApiController extends Controller
{
    public function index()
    {
        $users = User::with(['bank', 'roles'])
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->asUserRole()?->value,
                'role_label' => $u->asUserRole()?->label(),
                'bank_name' => $u->bank?->name,
                'bank_code' => $u->bank?->code,
            ]);

        return view('test_api', compact('users'));
    }
}
