<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLogService
{
    public function log(
        string $module,
        string $action,
        string $description,
        Model|int|null $record = null,
        Authenticatable|int|null $user = null,
        ?string $ipAddress = null,
    ): ActivityLog {
        return ActivityLog::query()->create([
            'user_id' => $user instanceof Authenticatable ? $user->getAuthIdentifier() : ($user ?? Auth::id()),
            'module' => $module,
            'action' => $action,
            'record_id' => $record instanceof Model ? $record->getKey() : $record,
            'description' => $description,
            'ip_address' => $ipAddress ?? request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
