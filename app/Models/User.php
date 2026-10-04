<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\PermissionCatalog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\Admin\PlatformSettings;

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
        'visibility_settings',
        'license_expires_at',
        'last_payment_months',
        'last_payment_amount',
        'blocked_features',
        'entry_limit',
        'entry_limit_mode',
        'admin_notes',
        'subscription_currency',
        'disabled_from_role',
        'disabled_at',
        'disabled_reason',
        'pos_settings',
        'is_active',
        'staff_portal_key',
        'attendance_settings',
        'timezone',
    ];

    /**
     * Roles that represent a business (tenant) owner account.
     */
    public const OWNER_ROLES = ['shop_owner', 'restaurant', 'merchant'];

    /**
     * The tenant (shop owner) id this user's data belongs to.
     * Employees resolve to their owner, everyone else to themselves.
     */
    public function ownerId(): ?int
    {
        if ($this->role === 'employee') {
            return $this->shop_owner_id ? (int) $this->shop_owner_id : null;
        }

        return $this->id ? (int) $this->id : null;
    }

    /**
     * True for shop_owner / restaurant / merchant accounts (not employees, admins or disabled).
     */
    public function isOwnerAccount(): bool
    {
        return in_array($this->role, self::OWNER_ROLES, true);
    }

    public function isDisabledOwner(): bool
    {
        return $this->role === 'disabled';
    }

    /**
     * True when this user (or the owner they work for) runs a restaurant account.
     */
    public function isRestaurantAccount(): bool
    {
        if ($this->role === 'restaurant') {
            return true;
        }

        return $this->role === 'employee'
            && $this->shop_owner_id
            && optional($this->shopOwner)->role === 'restaurant';
    }

    /**
     * Accept arrays for the JSON "permissions" column (legacy rows store a JSON string).
     */
    public function setPermissionsAttribute($value): void
    {
        if (is_array($value)) {
            $value = json_encode(array_values(array_unique($value)));
        }

        $this->attributes['permissions'] = $value === [] ? null : $value;
    }

    /**
     * Decode the POS layout preferences (null for accounts that never customised the POS).
     */
    public function getPosSettingsAttribute($value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function setPosSettingsAttribute($value): void
    {
        $this->attributes['pos_settings'] = is_array($value) ? json_encode($value) : $value;
    }


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
            'license_expires_at' => 'date',
            'visibility_settings' => 'array',
            'blocked_features' => 'array',
            'disabled_at' => 'datetime',
            'is_active' => 'boolean',
            'attendance_settings' => 'array',
        ];
    }

    /**
     * Get a visibility setting with a default of true (show by default)
     */
    public function getVisibilitySetting(string $key): bool
    {
        $settings = $this->visibility_settings ?? [];
        return isset($settings[$key]) ? (bool) $settings[$key] : true;
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
        return now()->startOfDay()->gt($this->temp_expires_at->copy()->startOfDay());
    }

    /**
     * Calculate the trial period days based on created_at and temp_expires_at
     */
    public function getCalculatedTrialPeriod(): ?int
    {
        if (!$this->isTempAccount() || !$this->temp_expires_at || !$this->created_at) {
            return null;
        }
        return (int) $this->created_at->diffInDays($this->temp_expires_at);
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
            $daysLeft = now()->startOfDay()->diffInDays($this->temp_expires_at->copy()->startOfDay(), false);
            if ($daysLeft <= 7 && $daysLeft >= 0) {
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
            ->whereDate('temp_expires_at', '<', now()->toDateString());
    }

    /**
     * Check if the full account license has expired
     */
    public function isLicenseExpired(): bool
    {
        if ($this->account_type !== 'full' || !$this->license_expires_at) {
            return false;
        }
        return now()->startOfDay()->gt($this->license_expires_at->copy()->startOfDay());
    }

    /**
     * Check if the full account license is expiring soon (within 30 days)
     */
    public function isLicenseExpiringSoon(): bool
    {
        if ($this->account_type !== 'full' || !$this->license_expires_at) {
            return false;
        }
        $daysLeft = now()->startOfDay()->diffInDays($this->license_expires_at->copy()->startOfDay(), false);
        return $daysLeft >= 0 && $daysLeft <= 30;
    }

    /**
     * Get days until license expires (negative if already expired)
     */
    public function getLicenseDaysLeft(): ?int
    {
        if ($this->account_type !== 'full' || !$this->license_expires_at) {
            return null;
        }
        return (int) now()->startOfDay()->diffInDays($this->license_expires_at->copy()->startOfDay(), false);
    }

    /**
     * Scope to get disabled expired temp accounts (ready for deletion)
     */
    public function scopeDisabledExpiredAccounts($query)
    {
        return $query->where('account_type', 'temp')
            ->whereNotNull('temp_expires_at')
            ->whereDate('temp_expires_at', '<', now()->toDateString())
            ->where('role', 'disabled');
    }

    // ── Feature / Tier helpers ────────────────────────────────────────────

    /**
     * The shop-owner record that governs this user's feature access.
     * For shop owners / admins it is themselves; for employees it is their owner.
     */
    public function featureOwner(): self
    {
        if ($this->role === 'employee') {
            return $this->shopOwner ?? $this;
        }
        return $this;
    }

    /**
     * Returns true when the feature is NOT blocked for this user.
     * Feature keys are defined in \App\Support\FeatureCatalog.
     */
    public function canAccessFeature(string $feature): bool
    {
        $owner = $this->featureOwner();
        $blocked = $owner->blocked_features ?? [];
        return !in_array($feature, $blocked, true);
    }

    /**
     * Entry limit for this user's shop (null = unlimited).
     */
    public function getEntryLimit(): ?int
    {
        return $this->featureOwner()->entry_limit;
    }

    /**
     * Combined entry count: bills + products + customers + purchase_bills.
     * Always scoped to the shop-owner's data.
     */
    public function getEntryUsage(): int
    {
        $ownerId = $this->role === 'employee' ? $this->shop_owner_id : $this->id;
        if (!$ownerId) return 0;
        return \App\Models\Bill::withoutGlobalScopes()->where('user_id', $ownerId)->count()
            + \App\Models\Product::withoutGlobalScopes()->where('user_id', $ownerId)->count()
            + \App\Models\Customer::withoutGlobalScopes()->where('user_id', $ownerId)->count()
            + \App\Models\PurchaseBill::withoutGlobalScopes()->where('user_id', $ownerId)->count();
    }

    /**
     * Returns usage percentage (0-100). Returns 0 when unlimited.
     */
    public function getEntryUsagePercent(): int
    {
        $limit = $this->getEntryLimit();
        if (!$limit) return 0;
        return (int) min(100, round(($this->getEntryUsage() / $limit) * 100));
    }

    public function getEntryRemaining(): ?int
    {
        $limit = $this->getEntryLimit();
        if (! $limit) {
            return null;
        }

        return max(0, $limit - $this->getEntryUsage());
    }

    public function subscriptionCurrency(): string
    {
        if ($this->subscription_currency) {
            return $this->subscription_currency;
        }

        return app(PlatformSettings::class)->get('default_currency', 'ILS');
    }

    public function businessRole(): string
    {
        if ($this->role === 'disabled') {
            return $this->disabled_from_role ?: 'shop_owner';
        }

        return $this->role;
    }

    public function isImpersonating(): bool
    {
        return session()->has('impersonator_id');
    }

    // ── Relationships ─────────────────────────────────────────────────────

    public function employees()
    {
        return $this->hasMany(User::class, 'shop_owner_id');
    }

    public function shopOwner()
    {
        return $this->belongsTo(User::class, 'shop_owner_id');
    }

    public function subscriptionPayments()
    {
        return $this->hasMany(SubscriptionPayment::class, 'user_id');
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
            return in_array($permission, PermissionCatalog::normalize($this->getPermissions()), true);
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

    public function revokeRememberedAccess(): void
    {
        DB::table('sessions')
            ->where(function ($query) {
                $query->where('user_id', $this->id);

                if ($this->session_id) {
                    $query->orWhere('id', $this->session_id);
                }
            })
            ->delete();

        $this->forceFill([
            'session_id' => null,
            'remember_token' => Str::random(60),
        ])->save();
    }
}
