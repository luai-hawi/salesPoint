<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

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
        'owner_name',
        'email',
        'password',
        'role',
        'shop_owner_id',
        'session_id',
        'details', // Added details field
        'phone_number',
        'subscription_paid',
        'subscription_cost',
        'product_warning_period',
        'product_deactivation_period',
        'permissions',
        'image_limit',
        'account_type',
        'temp_period_days',
        'temp_expires_at',
    ];


    /**
     * Logout all other sessions for this user
     */
    public function logoutOtherSessions($currentSessionId)
    {
        return DB::table('sessions')
            ->where('user_id', $this->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();
    }

    /**
     * Check if user has active sessions
     */
    public function hasActiveSessions()
    {
        return DB::table('sessions')
            ->where('user_id', $this->id)
            ->exists();
    }

    /**
     * Get count of active sessions
     */
    public function getActiveSessionsCount()
    {
        return DB::table('sessions')
            ->where('user_id', $this->id)
            ->count();
    }
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
            'temp_expires_at' => 'date',
        ];
    }

    /**
     * Check if this is a temporary account
     */
    public function isTempAccount(): bool
    {
        return $this->account_type === 'temp';
    }

    /**
     * Check if the temporary account has expired
     */
    public function isTempExpired(): bool
    {
        if (!$this->isTempAccount() || !$this->temp_expires_at) {
            return false;
        }
        return now()->greaterThan($this->temp_expires_at);
    }

    /**
     * Get the status label for temp accounts
     */
    public function getTempStatusLabel(): ?string
    {
        if ($this->account_type !== 'temp') {
            return null;
        }

        if ($this->isTempExpired()) {
            return 'expired';
        }

        if ($this->temp_expires_at) {
            $daysLeft = now()->diffInDays($this->temp_expires_at, false);
            if ($daysLeft <= 7 && $daysLeft > 0) {
                return 'expiring_soon';
            }
            return 'active';
        }

        return 'active';
    }

    /**
     * Scope to get only expired temp accounts
     */
    public function scopeExpiredTempAccounts($query)
    {
        return $query->where('account_type', 'temp')
            ->whereNotNull('temp_expires_at')
            ->where('temp_expires_at', '<', now());
    }

    /**
     * Scope to get disabled expired temp accounts (ready for deletion)
     */
    public function scopeDisabledExpiredAccounts($query)
    {
        return $query->where('account_type', 'temp')
            ->whereNotNull('temp_expires_at')
            ->where('temp_expires_at', '<')
            ->where('role', 'disabled');
    }

    public function employees()
    {
        return $this->hasMany(User::class, 'shop_owner_id');
    }

    public function shopOwner()
    {
        return $this->belongsTo(User::class, 'shop_owner_id');
    }

    /**
     * Check if user has a specific permission
     */
    public function hasPermission($permission)
    {
        // Admins and shop owners have all permissions
        if (in_array($this->role, ['admin', 'shop_owner', 'restaurant', 'merchant'])) {
            return true;
        }

        // For employees, check permissions array
        if ($this->role === 'employee' && $this->permissions) {
            $permissions = json_decode($this->permissions, true);
            return is_array($permissions) && in_array($permission, $permissions);
        }

        return false;
    }

    /**
     * Get user's permissions as array
     */
    public function getPermissions()
    {
        if ($this->permissions) {
            return json_decode($this->permissions, true) ?? [];
        }
        return [];
    }

    /**
     * Set user's permissions
     */
    public function setPermissions(array $permissions)
    {
        $this->permissions = json_encode(array_unique($permissions));
        $this->save();
    }
}
