<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $google_id
 * @property string|null $avatar_path
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $account_status
 * @property string|null $availability_status
 * @property Carbon|null $last_login_at
 * @property Carbon|null $last_seen_at
 * @property string|float|null $latitude
 * @property string|float|null $longitude
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string $role
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int $active_jobs_count
 */
#[Fillable(['name', 'email', 'google_id', 'password', 'role', 'phone', 'address', 'avatar_path', 'account_status', 'last_login_at', 'availability_status', 'last_seen_at', 'latitude', 'longitude'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

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
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    public function avatarUrl(): ?string
    {
        if (! filled($this->avatar_path)) {
            return null;
        }

        return config('filesystems.disks.public.driver') === 'local'
            ? '/storage/'.ltrim($this->avatar_path, '/')
            : Storage::disk('public')->url($this->avatar_path);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'superadmin';
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['superadmin', 'admin'], true);
    }

    public function isTechnician(): bool
    {
        return $this->role === 'technician';
    }

    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    public function hasActiveAccount(): bool
    {
        return (string) ($this->account_status ?? 'active') === 'active';
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'user_id');
    }

    /** @return HasMany<Booking, $this> */
    public function assignedBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'assigned_technician_id');
    }

    /** @return HasOne<TechnicianVerification, $this> */
    public function technicianVerification(): HasOne
    {
        return $this->hasOne(TechnicianVerification::class);
    }

    /** @return HasMany<Review, $this> */
    public function customerReviews(): HasMany
    {
        return $this->hasMany(Review::class, 'customer_id');
    }

    /** @return HasMany<Review, $this> */
    public function technicianReviews(): HasMany
    {
        return $this->hasMany(Review::class, 'technician_id');
    }

    /** @return HasMany<WalkInEntry, $this> */
    public function walkInEntries(): HasMany
    {
        return $this->hasMany(WalkInEntry::class, 'user_id');
    }

    /** @return HasMany<SupportTicket, $this> */
    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'user_id');
    }

    /** @return HasMany<SupportTicket, $this> */
    public function assignedSupportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'assigned_to');
    }

    /** @return HasMany<AuditLog, $this> */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'user_id');
    }
}
