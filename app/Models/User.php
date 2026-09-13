<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $guard_name = 'api';

    protected $attributes = [
        'receive_notifications' => false,
        'notification_channel' => 'whatsapp',
        'fcm_target_type' => 'token',
    ];

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'avatar',
        'status',
        'receive_notifications',
        'notification_channel',
        'last_login_at',
        'last_login_ip',
        'business_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'fcm_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
        'receive_notifications' => 'boolean',
    ];

    protected $appends = ['full_name', 'initials', 'has_fcm_token'];

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->status === 'inactive') {
                $user->forceFill([
                    'notification_channel' => 'whatsapp',
                    'receive_notifications' => false,
                ]);
            }

            if ($user->notification_channel !== 'push') {
                $user->forceFill([
                    'fcm_token' => null,
                    'fcm_target_type' => 'token',
                ]);
            }
        });
    }

    public function getFullNameAttribute()
    {
        return $this->first_name.' '.$this->last_name;
    }

    public function getInitialsAttribute()
    {
        return strtoupper(substr((string) $this->first_name, 0, 1).substr((string) $this->last_name, 0, 1));
    }

    public function getHasFcmTokenAttribute(): bool
    {
        return ! empty($this->fcm_token);
    }

    public function getFcmTargetTypeAttribute(?string $value): ?string
    {
        return $this->fcm_token ? ($value ?? 'token') : null;
    }

    public function updateLastLogin($ip = null)
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip ?? request()->ip(),
        ]);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%");
        });
    }

    /** El negocio que posee el usuario. */
    public function ownedBusiness()
    {
        return $this->hasOne(Business::class, 'user_id');
    }

    /** El negocio al que está asignado el usuario. */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    /** Configuración de nómina del usuario. */
    public function payrollConfig()
    {
        return $this->hasOne(UserPayrollConfig::class);
    }

    /** Adelantos de sueldo del usuario. */
    public function salaryAdvances()
    {
        return $this->hasMany(SalaryAdvance::class);
    }

    /** Registros de asistencia del usuario. */
    public function attendanceLogs()
    {
        return $this->hasMany(AttendanceLog::class);
    }

    /** Activos prestados al usuario. */
    public function assetLoans()
    {
        return $this->morphMany(AssetLoan::class, 'borrower');
    }
}
