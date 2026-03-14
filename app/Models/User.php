<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'employee_code',
        'email',
        'phone',
        'department_id',
        'role_id',
        'manager_id',
        'hod_id',
        'status',
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
            'password' => 'hashed',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
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
        $name = strtolower((string) optional($this->role)->name);
        $slug = strtolower((string) optional($this->role)->slug);

        return in_array($name, $roleNames, true) || in_array($slug, $roleNames, true);
    }

    public function roleKey(): string
    {
        $slug = strtolower((string) optional($this->role)->slug);

        if ($slug !== '') {
            return $slug;
        }

        return strtolower((string) optional($this->role)->name);
    }

    public function dashboardRole(): string
    {
        return match ($this->roleKey()) {
            'super_admin' => 'super_admin',
            'admin' => 'admin',
            'operations', 'dispatch', 'executive' => 'operations',
            'reviewer', 'hod', 'manager' => 'reviewer',
            'viewer' => 'viewer',
            default => 'viewer',
        };
    }
}
