<?php

namespace App\Services;

use App\Models\AuditTrail;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditTrailService
{
    public function record(
        string $module,
        string $event,
        Model $model,
        ?array $oldValue,
        ?array $newValue,
        Authenticatable|int|null $user = null,
    ): AuditTrail {
        $resolvedUser = $user instanceof Authenticatable
            ? $user->getAuthIdentifier()
            : ($user ?? Auth::id());

        return AuditTrail::query()->create([
            'module' => $module,
            'event' => $event,
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'changed_by' => $resolvedUser,
            'changed_at' => now(),
        ]);
    }
}