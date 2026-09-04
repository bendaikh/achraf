<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const STATUS_ACTIF = 'actif';

    public const STATUS_INACTIF = 'inactif';

    public const STATUS_SUSPENDU = 'suspendu';

    public const STATUSES = [
        self::STATUS_ACTIF => 'Actif',
        self::STATUS_INACTIF => 'Inactif',
        self::STATUS_SUSPENDU => 'Suspendu',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'hr_permissions',
        'collaborator_id',
        'primary_role_id',
        'status',
        'data_scope',
        'last_login_at',
        'invitation_token',
        'invitation_sent_at',
        'activated_at',
        'two_factor_enabled',
        'two_factor_secret',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'invitation_token',
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
            'hr_permissions' => 'array',
            'last_login_at' => 'datetime',
            'invitation_sent_at' => 'datetime',
            'activated_at' => 'datetime',
            'two_factor_enabled' => 'boolean',
        ];
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function primaryRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'primary_role_id');
    }

    public function collaborator(): BelongsTo
    {
        return $this->belongsTo(Collaborator::class);
    }

    public function permissionOverrides(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_user')
            ->withPivot('granted')
            ->withTimestamps();
    }

    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class, 'user_warehouses');
    }

    public function hasRole($role)
    {
        try {
            if ($this->primaryRole && $this->primaryRole->slug === $role) {
                return true;
            }

            return $this->roles()->where('slug', $role)->exists();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function isSuperAdmin()
    {
        try {
            return $this->hasRole('superadmin');
        } catch (\Exception $e) {
            return false;
        }
    }

    public function employee()
    {
        return $this->hasOne(Employee::class);
    }

    public function tablePreferences()
    {
        return $this->hasMany(UserTablePreference::class);
    }

    public function canHr(string $permission): bool
    {
        return \App\Support\HrPermission::allows($this, $permission);
    }

    public function canAccess(string $permissionKey): bool
    {
        return \App\Support\AccessPermission::allows($this, $permissionKey);
    }

    public function isAccountActive(): bool
    {
        return ($this->status ?? self::STATUS_ACTIF) === self::STATUS_ACTIF;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status ?? self::STATUS_ACTIF] ?? $this->status;
    }

    public function dataScopeLabel(): string
    {
        return \App\Support\PermissionCatalog::DATA_SCOPES[$this->data_scope ?? 'own']
            ?? ($this->data_scope ?? 'own');
    }
}
