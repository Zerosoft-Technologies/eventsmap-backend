<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes;

    const ROLE_USER = 'user';
    const ROLE_ADMIN = 'admin';
    const ROLE_SUPER_ADMIN = 'super_admin';

    const PROFILE_EVENT = 'event';
    const PROFILE_TALENT = 'talent';
    const PROFILE_ORGANIZER = 'organizer';
    const PROFILE_VENUE = 'venue';

    const ACCOUNT_FREE = 'free';
    const ACCOUNT_PREMIUM = 'premium';

    const STATUS_ACTIVE = 'active';
    const STATUS_PENDING_PAYMENT = 'pending_payment';
    const STATUS_SUSPENDED = 'suspended';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'profile_type',
        'account_type',
        'status',
        'billing_type',
        'full_name',
        'company_name',
        'vat_number',
        'vat_validated',
        'country',
        'address',
        'postal_code',
        'city',
        'stripe_customer_id',
        'stripe_subscription_id',
        'stripe_session_id',
        'premium_started_at',
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
            'is_active' => 'boolean',
            'vat_validated' => 'boolean',
        ];
    }

    /**
     * Check if the user has a free account.
     */
    public function isFreeAccount(): bool
    {
        return $this->account_type === self::ACCOUNT_FREE;
    }

    /**
     * Check if the user has a premium account.
     */
    public function isPremiumAccount(): bool
    {
        return $this->account_type === self::ACCOUNT_PREMIUM;
    }

    /**
     * Check if the user account status is active.
     */
    public function isAccountActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if the user's email is verified.
     */
    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * Check if user is an admin.
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN]);
    }

    /**
     * Check if user is a super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    /**
     * Scope to get only admin users.
     */
    public function scopeAdmins($query)
    {
        return $query->whereIn('role', [self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN]);
    }

    /**
     * Scope to get only active users.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by profile type.
     */
    public function scopeProfileType($query, string $type)
    {
        return $query->where('profile_type', $type);
    }

    /**
     * Scope to filter by account type.
     */
    public function scopeAccountType($query, string $type)
    {
        return $query->where('account_type', $type);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by country.
     */
    public function scopeCountry($query, string $country)
    {
        return $query->where('country', $country);
    }

    /**
     * Scope to get only regular (non-admin) users.
     */
    public function scopeRegularUsers($query)
    {
        return $query->where('role', self::ROLE_USER);
    }

    /**
     * Check if premium user requires payment completion.
     */
    public function requiresPayment(): bool
    {
        return $this->account_type === self::ACCOUNT_PREMIUM
            && $this->status !== self::STATUS_ACTIVE;
    }

    /**
     * Send the password reset notification.
     *
     * Override to use custom notification for API/SPA applications.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Get invitations received by this user.
     */
    public function receivedInvitations()
    {
        return $this->hasMany(EventInvitation::class, 'receiver_id');
    }

    /**
     * Get invitations sent by this user.
     */
    public function sentInvitations()
    {
        return $this->hasMany(EventInvitation::class, 'sender_id');
    }

    /**
     * Get accepted invitations for this user.
     */
    public function acceptedInvitations()
    {
        return $this->hasMany(EventInvitation::class, 'receiver_id')
            ->where('status', EventInvitation::STATUS_ACCEPTED);
    }

    /**
     * Get chat bans for this user.
     */
    public function chatBans()
    {
        return $this->hasMany(ChatBan::class, 'user_id');
    }

    /**
     * Check if user is banned from a specific event chat.
     */
    public function isBannedFromEventChat(int $eventId): bool
    {
        return $this->chatBans()
            ->forEvent($eventId)
            ->bans()
            ->active()
            ->exists();
    }

    /**
     * Check if user is muted in a specific event chat.
     */
    public function isMutedInEventChat(int $eventId): bool
    {
        return $this->chatBans()
            ->forEvent($eventId)
            ->mutes()
            ->active()
            ->exists();
    }

    /**
     * Get the gallery images for this user.
     */
    public function galleryImages()
    {
        return $this->hasMany(GalleryImage::class);
    }
}
