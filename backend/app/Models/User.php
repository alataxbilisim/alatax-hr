<?php

namespace App\Models;

use App\Enums\UserType;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /** Spatie PermissionSeeder ile aynı guard (API) */
    protected $guard_name = 'sanctum';

    protected $fillable = [
        'company_id',
        'name',
        'email',
        'phone',
        'avatar',
        'title',
        'department',
        'type',
        'password',
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'is_active',
        'last_login_at',
        'last_login_ip',
        'preferences',
        'invitation_token',
        'invited_at',
        'invitation_accepted_at',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_enabled' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'preferences' => 'array',
            'type' => UserType::class,
        ];
    }

    /**
     * Firma ilişkisi
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Employee ilişkisi (personel kaydı)
     */
    public function employee()
    {
        return $this->hasOne(Employee::class);
    }

    /**
     * Oluşturan kullanıcı
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Güncelleyen kullanıcı
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * SuperAdmin mi?
     */
    public function isSuperAdmin(): bool
    {
        return $this->type === UserType::SuperAdmin;
    }

    /**
     * Firma Admini mi?
     */
    public function isCompanyAdmin(): bool
    {
        return $this->type === UserType::CompanyAdmin;
    }

    /**
     * Normal kullanıcı mı?
     */
    public function isUser(): bool
    {
        return $this->type === UserType::User;
    }

    /**
     * Kullanıcı aktif mi?
     */
    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * Şifre sıfırlama bildirimi — SPA reset URL'si (queued).
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Belirli bir modüle erişimi var mı?
     */
    public function hasModuleAccess(string $moduleSlug): bool
    {
        // SuperAdmin her şeye erişebilir
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Firma yoksa erişim yok
        if (! $this->company) {
            return false;
        }

        return $this->company->hasActiveModule($moduleSlug);
    }

    /**
     * Son giriş bilgisini güncelle
     */
    public function updateLastLogin(?string $ip = null): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ]);
    }

    /**
     * Tercih değeri al
     */
    public function getPreference(string $key, $default = null)
    {
        return data_get($this->preferences, $key, $default);
    }

    /**
     * Tercih değeri kaydet
     */
    public function setPreference(string $key, $value): void
    {
        $preferences = $this->preferences ?? [];
        data_set($preferences, $key, $value);
        $this->preferences = $preferences;
        $this->save();
    }

    /**
     * Tema tercihi (dark/light)
     */
    public function getTheme(): string
    {
        return $this->getPreference('theme', 'dark');
    }

    /**
     * Dil tercihi
     */
    public function getLocale(): string
    {
        return $this->getPreference('locale', 'tr');
    }

    /**
     * Guard name for Spatie Permission
     */
    protected function getDefaultGuardName(): string
    {
        return 'sanctum';
    }
}
