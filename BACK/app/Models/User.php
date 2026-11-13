<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasTableQuery;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use BelongsToCompany;
    use HasTableQuery;
    use Notifiable;
    use SoftDeletes;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * role_id is intentionally NOT mass assignable: assigning a role is a
     * privileged action and must go through UserPolicy::assignRole.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'first_name',
        'last_name',
        'birth_date',
        'hire_date',
        'photo',
        'phone',
        'vacation_days_total',
        'team_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
        // session_token holds the sha256 hash of the active API token:
        // never expose it in JSON responses (e.g. GET /api/employees/{id}).
        'session_token',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Permission codes resolved for this user (memoized per instance).
     *
     * @var array<int, string>|null
     */
    private ?array $permissionCodesCache = null;

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
            'is_super_admin' => 'boolean',
            'last_login_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    /**
     * Platform owner: belongs to no company and can see every company.
     * Never mass assignable; only set from seeders / console.
     */
    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Admin of the company: its role is flagged is_admin and bypasses every
     * permission check (see Gate::before in AppServiceProvider).
     */
    public function isAdmin(): bool
    {
        return $this->isSuperAdmin() || (bool) $this->role?->is_admin;
    }

    /**
     * Permission codes granted to this user through their single role.
     * Admins implicitly hold every permission in the catalog.
     *
     * @return array<int, string>
     */
    public function permissionCodes(): array
    {
        if ($this->permissionCodesCache !== null) {
            return $this->permissionCodesCache;
        }

        if ($this->isAdmin()) {
            $codes = Permission::query()->orderBy('code')->pluck('code')->all();
        } elseif ($this->role) {
            $codes = $this->role->permissions()->orderBy('code')->pluck('code')->all();
        } else {
            $codes = [];
        }

        return $this->permissionCodesCache = array_values($codes);
    }

    public function hasPermission(string $code): bool
    {
        return $this->isAdmin() || in_array($code, $this->permissionCodes(), true);
    }

    /**
     * Notifications this user has sent (chat messages).
     */
    public function notificationsSent()
    {
        return $this->hasMany(Notification::class, 'sender_id');
    }

    /**
     * Notifications this user has received.
     */
    public function notificationsReceived()
    {
        return $this->hasMany(Notification::class, 'receiver_id');
    }

    /**
     * Shortcut for unread received messages.
     */
    public function unreadReceived()
    {
        return $this->notificationsReceived()->where('read', 0);
    }
}
