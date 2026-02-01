<?php

namespace App\Http\Controllers;

use App\Enums\TenantUserRole;
use App\Http\Resources\TenantUserResource;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display the list of tenant users.
     */
    public function index(): Response
    {
        Gate::authorize('can-manage-users');

        $tenant = tenant();

        $users = $tenant->users()
            ->select(['users.id', 'users.name', 'users.email'])
            ->orderBy('users.name')
            ->get();

        return Inertia::render('Users/Index', [
            'users' => TenantUserResource::collection($users),
            'roles' => collect(TenantUserRole::cases())->map(fn (TenantUserRole $role) => [
                'value' => $role->value,
                'label' => $role->label(),
            ]),
        ]);
    }
}
