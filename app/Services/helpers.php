<?php

use App\Models\User;
use App\Services\AccessControlService;
use Illuminate\Support\Facades\Auth;

if (! function_exists('userCan')) {
    function userCan(string $permission): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->hasPermission($permission);
    }
}

if (! function_exists('userRoleKey')) {
    function userRoleKey(): string
    {
        $user = Auth::user();

        return $user instanceof User
            ? app(AccessControlService::class)->roleKey($user)
            : 'viewer';
    }
}
