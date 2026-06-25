<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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

    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $fillable = [
        'fname',
        'lname',
        'mname',
        'email',
        'password',
        'phone',
        'address',
        'gender',
        'date_of_birth',
        'profile_picture',
        'role',
        'is_active',
        'last_login',
        'last_logout',
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

    /**
     * Get all funding requests submitted by this user
     */
    public function fundingRequests()
    {
        return $this->hasMany(Funding::class, 'user_id', 'id');
    }

    /**
     * Get all IoT boxes owned by this user
     */
    public function iotBoxes()
    {
        return $this->hasMany(IotBox::class, 'user_id', 'id');
    }

    /**
     * Get all donations tracked by this user
     */
    public function donations()
    {
        return $this->hasMany(Donation::class, 'user_id', 'id');
    }

    /**
     * Get all approvals made by this user (if admin/moderator)
     */
    public function approvals()
    {
        return $this->hasMany(FundingApproval::class, 'approved_by', 'id');
    }

    /**
     * Get the beneficiary profile for this user (if any)
     */
    public function beneficiaryProfile()
    {
        return $this->hasOne(BeneficiaryProfile::class, 'user_id', 'id');
    }

    /**
     * Get all audit logs for this user
     */
    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'user_id', 'id');
    }

    /**
     * Get all notification logs for this user
     */
    public function notificationLogs()
    {
        return $this->hasMany(NotificationLog::class, 'user_id', 'id');
    }

    /**
     * Get all activity logs for this user
     */
    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class, 'user_id', 'id');
    }

    /**
     * Get all beneficiary profiles verified by this user
     */
    public function verifiedProfiles()
    {
        return $this->hasMany(BeneficiaryProfile::class, 'verified_by', 'id');
    }

    public function receivedDonations()
    {
        return $this->hasMany(Donation::class, 'beneficiary_id', 'id');
    }

    /**
     * Check if user has admin role
     */
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user has provider role
     */
    public function isProvider()
    {
        return $this->role === 'provider';
    }

    /**
     * Check if user has moderator role
     */
    public function isModerator()
    {
        return $this->role === 'moderator';
    }

    /**
     * Check if user has editor role
     */
    public function isEditor()
    {
        return $this->role === 'editor';
    }

    /**
     * Check if user has user role
     */
    public function isUser()
    {
        return $this->role === 'user';
    }

    /**
     * Check if user is active
     */
    public function isActive()
    {
        return $this->is_active === true;
    }

    /**
     * Check if user is inactive
     */
    public function isInactive()
    {
        return $this->is_active === false;
    }

    public function forgotPassword()
    {
        return $this->hasOne(PasswordReset::class, 'user_id', 'id');
    }

    public function loginAuthCode()
    {
        return $this->hasOne(LoginAuthCode::class, 'user_id', 'id');
    }

    /**
     * Get the user's full name.
     */
    public function getNameAttribute()
    {
        return $this->fname . ' ' . $this->lname;
    }
}
