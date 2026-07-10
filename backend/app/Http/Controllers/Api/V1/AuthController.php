<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends BaseController
{
    /**
     * Kullanıcı girişi
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            // Başarısız giriş logla
            ActivityLog::create([
                'action' => 'login_failed',
                'description' => 'Başarısız giriş denemesi: ' . $request->email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'is_successful' => false,
            ]);

            return $this->error('E-posta veya şifre hatalı', 401);
        }

        // Kullanıcı aktif mi?
        if (!$user->is_active) {
            return $this->error('Hesabınız devre dışı bırakılmış. Lütfen yöneticinizle iletişime geçin.', 403);
        }

        // Firma kontrolü (SuperAdmin hariç)
        if (!$user->isSuperAdmin()) {
            if (!$user->company) {
                return $this->error('Hesabınız bir firmaya bağlı değil.', 403);
            }

            if (!$user->company->isActive() && !$user->company->status === 'trial') {
                return $this->error('Firma hesabınız aktif değil. Lütfen yöneticinizle iletişime geçin.', 403);
            }

            // Trial süresi kontrolü
            if ($user->company->isTrialExpired()) {
                return $this->error('Deneme süreniz dolmuş. Lütfen abonelik planınızı yükseltin.', 403);
            }
        }

        // Portal login kontrolü
        if ($request->has('portal_login') && $request->boolean('portal_login')) {
            // User'ın employee kaydı var mı?
            $employee = $user->employee;
            if (!$employee) {
                return $this->error('Portal erişim yetkiniz yok', 403);
            }
            
            // Employee aktif mi?
            if ($employee->status !== 'active') {
                return $this->error('Personel kaydınız aktif değil', 403);
            }
            
            // Portal token oluştur
            $token = $user->createToken('portal-token', ['portal:access'])->plainTextToken;
            
            // Son giriş bilgisini güncelle
            $user->updateLastLogin($request->ip());
            
            // Başarılı giriş logla
            ActivityLog::log('login', $user, 'Portal girişi yapıldı');
            
            return $this->success([
                'user' => $this->formatUser($user),
                'employee' => $employee,
                'token' => $token,
                'type' => 'portal',
            ], 'Portal girişi başarılı');
        }

        // Normal token oluştur
        $token = $user->createToken('auth-token')->plainTextToken;

        // Son giriş bilgisini güncelle
        $user->updateLastLogin($request->ip());

        // Başarılı giriş logla
        ActivityLog::log('login', $user, 'Kullanıcı girişi yapıldı');

        return $this->success([
            'user' => $this->formatUser($user),
            'token' => $token,
        ], 'Giriş başarılı');
    }

    /**
     * Kullanıcı çıkışı
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Mevcut token'ı sil
        $request->user()->currentAccessToken()->delete();

        // Log
        ActivityLog::log('logout', $user, 'Kullanıcı çıkışı yapıldı');

        return $this->success(null, 'Çıkış başarılı');
    }

    /**
     * Mevcut kullanıcı bilgisi
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('company');

        return $this->success([
            'user' => $this->formatUser($user),
        ]);
    }

    /**
     * Profil güncelleme
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|nullable|string|max:20',
            'title' => 'sometimes|nullable|string|max:100',
            'department' => 'sometimes|nullable|string|max:100',
            'avatar' => 'sometimes|nullable|image|mimes:jpg,jpeg,png|max:2048',
            'preferences' => 'sometimes|array',
            'preferences.theme' => 'sometimes|in:dark,light',
            'preferences.locale' => 'sometimes|in:tr,en',
        ]);

        // Avatar yükleme
        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $validated['avatar'] = $path;
        }

        // Preferences merge
        if (isset($validated['preferences'])) {
            $validated['preferences'] = array_merge(
                $user->preferences ?? [],
                $validated['preferences']
            );
        }

        $user->update($validated);

        ActivityLog::log('update', $user, 'Profil güncellendi');

        return $this->success([
            'user' => $this->formatUser($user->fresh()),
        ], 'Profil güncellendi');
    }

    /**
     * Şifre güncelleme
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
        ]);

        $user = $request->user();

        // Mevcut şifre kontrolü
        if (!Hash::check($request->current_password, $user->password)) {
            return $this->error('Mevcut şifreniz hatalı', 422, [
                'current_password' => ['Mevcut şifreniz hatalı'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Diğer cihazlardaki token'ları sil
        $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        ActivityLog::log('password_change', $user, 'Şifre değiştirildi');

        return $this->success(null, 'Şifreniz başarıyla güncellendi');
    }

    /**
     * Şifremi unuttum
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return $this->success(null, 'Şifre sıfırlama linki e-posta adresinize gönderildi');
        }

        return $this->error('E-posta gönderilemedi. Lütfen e-posta adresinizi kontrol edin.', 400);
    }

    /**
     * Şifre sıfırlama
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->update([
                    'password' => Hash::make($password),
                ]);
                
                // Tüm token'ları sil
                $user->tokens()->delete();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return $this->success(null, 'Şifreniz başarıyla sıfırlandı');
        }

        return $this->error('Şifre sıfırlanamadı. Link geçersiz veya süresi dolmuş olabilir.', 400);
    }

    /**
     * Yeni firma kaydı
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
            'phone' => 'nullable|string|max:20',
        ]);

        // Firma oluştur
        $company = \App\Models\Company::create([
            'name' => $validated['company_name'],
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
            'package_type' => 'starter',
            'user_limit' => 5,
        ]);

        // Core modülleri aktifle
        $coreModules = \App\Models\Module::where('is_core', true)->get();
        foreach ($coreModules as $module) {
            $company->modules()->attach($module->id, [
                'is_active' => true,
                'activated_at' => now(),
            ]);
        }

        // Admin kullanıcı oluştur
        $user = User::create([
            'company_id' => $company->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'type' => 'company_admin',
            'is_active' => true,
            'preferences' => [
                'theme' => 'dark',
                'locale' => 'tr',
            ],
        ]);

        // Admin rolünü ata
        $user->assignRole('admin');

        // Token oluştur
        $token = $user->createToken('auth-token')->plainTextToken;

        // Log
        ActivityLog::log('register', $company, 'Yeni firma kaydı oluşturuldu');

        return $this->created([
            'user' => $this->formatUser($user->load('company')),
            'token' => $token,
        ], 'Kayıt başarılı. 14 günlük deneme süreniz başladı.');
    }

    /**
     * Kullanıcı bilgilerini formatla
     */
    private function formatUser(User $user): array
    {
        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
            'title' => $user->title,
            'department' => $user->department,
            'type' => $user->type,
            'is_active' => $user->is_active,
            'preferences' => $user->preferences ?? ['theme' => 'dark', 'locale' => 'tr'],
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'roles' => $user->getRoleNames(),
        ];

        if ($user->company) {
            // Her zaman güncel company bilgilerini çek (cached değil)
            $company = $user->company->fresh();
            $activeModules = $company->activeModules()->pluck('slug')->toArray();
            $data['company'] = [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'logo' => $company->logo ? asset('storage/' . $company->logo) : null,
                'status' => $company->status,
                'package_type' => $company->package_type,
                'active_modules' => $activeModules,
            ];
        }

        return $data;
    }
}

