<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'employee_code',
        'designation',
        'email',
        'phone',
        'department_id',
        'role_id',
        'manager_id',
        'hod_id',
        'status',
        'must_change_password',
        'password_changed_at',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function requiresPasswordChange(): bool
    {
        return (bool) $this->must_change_password;
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function hod(): BelongsTo
    {
        return $this->belongsTo(self::class, 'hod_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    public function hodReports(): HasMany
    {
        return $this->hasMany(self::class, 'hod_id');
    }

    public function executiveMappings(): HasMany
    {
        return $this->hasMany(ExecutiveMapping::class, 'executive_user_id');
    }

    public function executiveReplacementHistoryAsOld(): HasMany
    {
        return $this->hasMany(ExecutiveReplacementHistory::class, 'old_executive_id');
    }

    public function executiveReplacementHistoryAsNew(): HasMany
    {
        return $this->hasMany(ExecutiveReplacementHistory::class, 'new_executive_id');
    }

    public function hasRole(string|array $roles): bool
    {
        $roleNames = array_map('strtolower', (array) $roles);
        $assignedRoles = $this->assignedRoleModels()->pluck('slug')
            ->merge($this->assignedRoleModels()->pluck('name'))
            ->map(fn ($value) => strtolower((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $primaryRoleSlug = strtolower((string) optional($this->role)->slug);
        $primaryRoleName = strtolower((string) optional($this->role)->name);

        return in_array($primaryRoleSlug, $roleNames, true)
            || in_array($primaryRoleName, $roleNames, true)
            || count(array_intersect($assignedRoles, $roleNames)) > 0;
    }

    public function hasPermission(string $permission): bool
    {
        $roleKey = $this->roleKey();

        if ($roleKey === 'super_admin') {
            return true;
        }

        [$module, $action] = array_pad(explode('.', $permission, 2), 2, null);

        if (! $module || ! $action) {
            return false;
        }

        $allowedPermissions = config('erp.role_permissions.' . $roleKey, []);

        return $allowedPermissions === ['*']
            || in_array($permission, $allowedPermissions, true);
    }

    public function syncRoles(array $roleIds): void
    {
        if (! Schema::hasTable('user_roles')) {
            $this->forceFill([
                'role_id' => collect($roleIds)->filter()->first(),
            ])->save();

            return;
        }

        $roleIds = collect($roleIds)
            ->map(fn ($roleId) => (int) $roleId)
            ->filter()
            ->unique()
            ->values();

        $this->roles()->sync($roleIds->all());

        $primaryRoleId = Role::query()
            ->whereIn('id', $roleIds->all())
            ->get(['id', 'slug'])
            ->sortBy(fn (Role $role) => array_search($role->slug, config('erp.role_priority', []), true))
            ->first()?->id;

        $this->forceFill([
            'role_id' => $primaryRoleId,
        ])->save();

        $this->unsetRelation('roles');
        $this->unsetRelation('role');
    }

    public function roleKey(): string
    {
        $assignedRole = $this->assignedRoleModels()
            ->sortBy(fn (Role $role) => array_search($role->slug, config('erp.role_priority', []), true))
            ->first();

        $slug = strtolower((string) ($assignedRole?->slug ?: optional($this->role)->slug));
        $slug = $this->normalizeRoleSlug($slug);

        if ($slug !== '') {
            return $slug;
        }

        return $this->normalizeRoleSlug(strtolower((string) ($assignedRole?->name ?: optional($this->role)->name)));
    }

    public function roleNames(): string
    {
        $roles = $this->assignedRoleModels()->pluck('name')->filter()->values();

        if ($roles->isNotEmpty()) {
            return $roles->implode(', ');
        }

        return (string) optional($this->role)->name;
    }

    public function dashboardRole(): string
    {
        return match ($this->roleKey()) {
            'super_admin' => 'super_admin',
            'admin' => 'admin',
            'operations' => 'operations',
            'reviewer' => 'reviewer',
            'viewer' => 'viewer',
            default => 'viewer',
        };
    }

    protected function assignedRoleModels(): Collection
    {
        $roles = collect();

        if (Schema::hasTable('user_roles')) {
            $roles = $this->relationLoaded('roles')
                ? collect($this->getRelation('roles'))
                : $this->roles()->get();
        }

        $primaryRole = $this->relationLoaded('role') ? $this->getRelation('role') : $this->role()->first();

        if ($primaryRole) {
            $roles->push($primaryRole);
        }

        return $roles
            ->filter()
            ->unique('id')
            ->values();
    }

    protected function normalizeRoleSlug(string $role): string
    {
        return match ($role) {
            'hod', 'manager', 'dispatch', 'executive' => 'operations',
            default => $role,
        };
    }
}
