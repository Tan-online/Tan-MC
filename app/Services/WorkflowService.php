<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowAction;
use App\Models\WorkflowStep;
use Illuminate\Database\Eloquent\Model;

class WorkflowService
{
    public function workflow(string $code): ?Workflow
    {
        return Workflow::query()
            ->with('steps.role:id,name,slug')
            ->where('code', $code)
            ->where('is_active', true)
            ->first();
    }

    public function record(string $workflowCode, string $action, Model $record, User $user, ?string $description = null): ?WorkflowAction
    {
        $workflow = $this->workflow($workflowCode);

        if (! $workflow) {
            return null;
        }

        $step = $workflow->steps
            ->first(fn (WorkflowStep $step) => strtolower($step->action) === strtolower($action));

        return WorkflowAction::query()->create([
            'workflow_id' => $workflow->id,
            'workflow_step_id' => $step?->id,
            'user_id' => $user->id,
            'actionable_type' => $record::class,
            'actionable_id' => $record->getKey(),
            'action' => $action,
            'description' => $description,
        ]);
    }

    public function canPerform(string $workflowCode, string $action, User $user): bool
    {
        $workflow = $this->workflow($workflowCode);

        if (! $workflow) {
            return false;
        }

        $step = $workflow->steps
            ->first(fn (WorkflowStep $step) => strtolower($step->action) === strtolower($action));

        if (! $step) {
            return false;
        }

        $role = $step->role;

        return $role instanceof Role && $user->hasRole($role->slug);
    }
}
