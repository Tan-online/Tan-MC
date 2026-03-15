<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

if (! function_exists('userCan')) {
    function userCan(string $permission): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->hasPermission($permission);
    }
}
