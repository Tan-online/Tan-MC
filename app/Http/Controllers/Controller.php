<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

abstract class Controller
{
    protected function authorizePermission(string $permission): void
    {
        abort_unless(userCan($permission), 403);
    }

    protected function logActivity(
        string $module,
        string $action,
        string $description,
        Model|int|null $record = null,
        Authenticatable|int|null $user = null
    ): void {
        app(ActivityLogService::class)->log($module, $action, $description, $record, $user);
    }
}
