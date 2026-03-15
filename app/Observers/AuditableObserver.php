<?php

namespace App\Observers;

use App\Services\DashboardStatsService;
use App\Services\AuditTrailService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class AuditableObserver
{
    public function __construct(
        private readonly AuditTrailService $auditTrailService,
        private readonly DashboardStatsService $dashboardStatsService,
    ) {
    }

    public function created(Model $model): void
    {
        $this->dashboardStatsService->forget();

        $this->auditTrailService->record(
            $this->module($model),
            'create',
            $model,
            null,
            $this->snapshot($model)
        );
    }

    public function updated(Model $model): void
    {
        $changes = Arr::except($model->getChanges(), $this->excludedAttributes($model));

        if ($changes === []) {
            return;
        }

        $this->dashboardStatsService->forget();

        $keys = array_keys($changes);

        $this->auditTrailService->record(
            $this->module($model),
            'update',
            $model,
            Arr::only($this->normalizedArray($model->getOriginal()), $keys),
            Arr::only($this->snapshot($model), $keys)
        );
    }

    public function deleted(Model $model): void
    {
        $this->dashboardStatsService->forget();

        $this->auditTrailService->record(
            $this->module($model),
            'delete',
            $model,
            $this->snapshot($model),
            null
        );
    }

    private function snapshot(Model $model): array
    {
        return Arr::except($this->normalizedArray($model->getAttributes()), $this->excludedAttributes($model));
    }

    private function normalizedArray(array $attributes): array
    {
        foreach ($attributes as $key => $value) {
            if ($value instanceof \DateTimeInterface) {
                $attributes[$key] = $value->format('Y-m-d H:i:s');
            }
        }

        return $attributes;
    }

    private function excludedAttributes(Model $model): array
    {
        $defaults = ['updated_at'];

        if (method_exists($model, 'auditExcludedAttributes')) {
            return array_values(array_unique(array_merge($defaults, $model->auditExcludedAttributes())));
        }

        return $defaults;
    }

    private function module(Model $model): string
    {
        if (method_exists($model, 'auditModule')) {
            return $model->auditModule();
        }

        return $model->getTable();
    }
}