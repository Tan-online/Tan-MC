<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Str;

class AccessControlService
{
    /**
     * @var array<int, array<int>>
     */
    private array $visibleExecutiveIdsCache = [];

    public function roleKey(?User $user): string
    {
        return $user?->roleKey() ?? 'viewer';
    }

    public function hasRole(?User $user, string|array $roles): bool
    {
        if (! $user) {
            return false;
        }

        return in_array($this->roleKey($user), (array) $roles, true);
    }

    public function canManageUsers(?User $user): bool
    {
        return $this->hasRole($user, 'super_admin');
    }

    public function isOperationsScoped(?User $user): bool
    {
        return $this->hasRole($user, 'operations');
    }

    public function isOperationsSupervisor(?User $user): bool
    {
        if (! $user || ! $this->isOperationsScoped($user)) {
            return false;
        }

        $designation = strtolower(trim((string) $user->designation));

        return $user->hasRole(['manager', 'hod'])
            || Str::contains($designation, ['manager', 'hod', 'head']);
    }

    /**
     * @return list<int>
     */
    public function visibleExecutiveUserIds(User $user): array
    {
        if (! $this->isOperationsScoped($user)) {
            return [];
        }

        if (array_key_exists($user->id, $this->visibleExecutiveIdsCache)) {
            return $this->visibleExecutiveIdsCache[$user->id];
        }

        $query = User::query()->select('id');

        if ($this->isOperationsSupervisor($user)) {
            $query->where(function (EloquentBuilder $builder) use ($user) {
                $builder
                    ->where('manager_id', $user->id)
                    ->orWhere('hod_id', $user->id);
            });
        } else {
            $query->whereKey($user->id);
        }

        $ids = $query
            ->pluck('id')
            ->push($user->id)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $this->visibleExecutiveIdsCache[$user->id] = $ids;
    }

    public function canViewMenuItem(?User $user, array $item): bool
    {
        if (! $user) {
            return false;
        }

        $roles = $item['roles'] ?? [];

        if ($roles !== [] && ! in_array($this->roleKey($user), $roles, true)) {
            return false;
        }

        $permission = $item['permission'] ?? null;

        return ! is_string($permission) || $user->hasPermission($permission);
    }

    public function scopeClients(EloquentBuilder|QueryBuilder $query, User $user, string $table = 'clients'): EloquentBuilder|QueryBuilder
    {
        if (! $this->isOperationsScoped($user)) {
            return $query;
        }

        $visibleIds = $this->visibleExecutiveUserIds($user);

        return $query->whereExists(function (QueryBuilder $builder) use ($table, $visibleIds) {
            $builder->selectRaw('1')
                ->from('executive_mappings as em')
                ->whereColumn('em.client_id', $table . '.id')
                ->where('em.is_active', true)
                ->whereIn('em.executive_user_id', $visibleIds);
        });
    }

    public function scopeLocations(EloquentBuilder|QueryBuilder $query, User $user, string $table = 'locations'): EloquentBuilder|QueryBuilder
    {
        if (! $this->isOperationsScoped($user)) {
            return $query;
        }

        $visibleIds = $this->visibleExecutiveUserIds($user);

        return $query->whereExists(function (QueryBuilder $builder) use ($table, $visibleIds) {
            $builder->selectRaw('1')
                ->from('executive_mappings as em')
                ->whereColumn('em.location_id', $table . '.id')
                ->where('em.is_active', true)
                ->whereIn('em.executive_user_id', $visibleIds);
        });
    }

    public function scopeContracts(EloquentBuilder|QueryBuilder $query, User $user, string $table = 'contracts'): EloquentBuilder|QueryBuilder
    {
        if (! $this->isOperationsScoped($user)) {
            return $query;
        }

        $visibleIds = $this->visibleExecutiveUserIds($user);

        return $query->whereExists(function (QueryBuilder $builder) use ($table, $visibleIds) {
            $builder->selectRaw('1')
                ->from('executive_mappings as em')
                ->where('em.is_active', true)
                ->whereIn('em.executive_user_id', $visibleIds)
                ->whereColumn('em.client_id', $table . '.client_id')
                ->where(function (QueryBuilder $assignment) use ($table) {
                    $assignment
                        ->whereColumn('em.contract_id', $table . '.id')
                        ->orWhereColumn('em.location_id', $table . '.location_id')
                        ->orWhereExists(function (QueryBuilder $pivot) use ($table) {
                            $pivot->selectRaw('1')
                                ->from('contract_location as cl')
                                ->whereColumn('cl.contract_id', $table . '.id')
                                ->whereColumn('cl.location_id', 'em.location_id');
                        });
                });
        });
    }

    public function scopeServiceOrders(EloquentBuilder|QueryBuilder $query, User $user, string $table = 'service_orders'): EloquentBuilder|QueryBuilder
    {
        if (! $this->isOperationsScoped($user)) {
            return $query;
        }

        $visibleIds = $this->visibleExecutiveUserIds($user);

        return $query->whereExists(function (QueryBuilder $builder) use ($table, $visibleIds) {
            $builder->selectRaw('1')
                ->from('executive_mappings as em')
                ->join('contracts as scoped_contracts', 'scoped_contracts.client_id', '=', 'em.client_id')
                ->whereColumn('scoped_contracts.id', $table . '.contract_id')
                ->whereExists(function (QueryBuilder $locationScope) use ($table) {
                    $locationScope->selectRaw('1')
                        ->from('service_order_location as sol')
                        ->whereColumn('sol.service_order_id', $table . '.id')
                        ->whereColumn('sol.location_id', 'em.location_id');
                })
                ->where('em.is_active', true)
                ->whereIn('em.executive_user_id', $visibleIds)
                ->where(function (QueryBuilder $assignment) use ($table) {
                    $assignment
                        ->whereColumn('em.contract_id', $table . '.contract_id')
                        ->orWhereNull('em.contract_id');
                });
        });
    }

    public function scopeDispatchEntries(EloquentBuilder|QueryBuilder $query, User $user, string $table = 'dispatch_entries'): EloquentBuilder|QueryBuilder
    {
        if (! $this->isOperationsScoped($user)) {
            return $query;
        }

        $visibleIds = $this->visibleExecutiveUserIds($user);

        return $query->whereExists(function (QueryBuilder $builder) use ($table, $visibleIds) {
            $builder->selectRaw('1')
                ->from('service_orders as so')
                ->whereColumn('so.id', $table . '.service_order_id')
                ->whereExists(function (QueryBuilder $scoped) use ($visibleIds) {
                    $scoped->selectRaw('1')
                        ->from('executive_mappings as em')
                        ->join('contracts as scoped_contracts', 'scoped_contracts.client_id', '=', 'em.client_id')
                        ->whereColumn('scoped_contracts.id', 'so.contract_id')
                        ->whereExists(function (QueryBuilder $locationScope) {
                            $locationScope->selectRaw('1')
                                ->from('service_order_location as sol')
                                ->whereColumn('sol.service_order_id', 'so.id')
                                ->whereColumn('sol.location_id', 'em.location_id');
                        })
                        ->where('em.is_active', true)
                        ->whereIn('em.executive_user_id', $visibleIds)
                        ->where(function (QueryBuilder $assignment) {
                            $assignment
                                ->whereColumn('em.contract_id', 'so.contract_id')
                                ->orWhereNull('em.contract_id');
                        });
                });
        });
    }

    public function scopeMusterExpected(EloquentBuilder|QueryBuilder $query, User $user, string $table = 'muster_expected'): EloquentBuilder|QueryBuilder
    {
        if (! $this->isOperationsScoped($user)) {
            return $query;
        }

        $visibleIds = $this->visibleExecutiveUserIds($user);

        return $query->whereExists(function (QueryBuilder $builder) use ($table, $visibleIds) {
            $builder->selectRaw('1')
                ->from('executive_mappings as em')
                ->whereColumn('em.id', $table . '.executive_mapping_id')
                ->where('em.is_active', true)
                ->whereIn('em.executive_user_id', $visibleIds);
        });
    }
}