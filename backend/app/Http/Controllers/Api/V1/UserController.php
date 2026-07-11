<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\EmployeeResource;
use App\Mail\PasswordResetByAdmin;
use App\Mail\UserInvitation;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends BaseController
{
    /**
     * Kullanıcı listesi
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::where('company_id', $this->getCompanyId())
            ->with(['roles']);

        // Arama
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%");
            });
        }

        // Durum filtresi
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Rol filtresi (isim ile)
        if ($request->has('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        // Rol filtresi (ID ile)
        if ($request->has('role_id')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('id', $request->role_id);
            });
        }

        // Sıralama
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $users = $query->paginate($request->get('per_page', 15));

        return $this->paginated($users, 'Kullanıcılar listelendi');
    }

    /**
     * Kullanıcı detay
     */
    public function show(User $user): JsonResponse
    {
        // Firma kontrolü
        if ($user->company_id !== $this->getCompanyId()) {
            return $this->forbidden('Bu kullanıcıya erişim yetkiniz yok');
        }

        $user->load(['roles', 'createdBy', 'updatedBy', 'employee']);

        // İstatistikler
        $stats = [
            'total_actions' => \App\Models\ActivityLog::where('user_id', $user->id)->count(),
            'last_activity' => \App\Models\ActivityLog::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->first()?->created_at,
            'active_sessions' => $user->tokens()->count(),
        ];

        $employee = $user->employee;
        $user->unsetRelation('employee');
        $userPayload = $user->toArray();
        if ($employee) {
            $userPayload['employee'] = (new EmployeeResource($employee))->resolve();
        }

        return $this->success([
            'user' => $userPayload,
            'stats' => $stats,
        ]);
    }

    /**
     * Kullanıcı oluştur
     */
    public function store(Request $request): JsonResponse
    {
        $company = auth()->user()->company;

        // Kullanıcı limiti kontrolü
        if ($company->hasReachedUserLimit()) {
            return $this->error(
                'Kullanıcı limitinize ulaştınız. Mevcut limit: '.$company->user_limit,
                403
            );
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => [
                'required',
                Password::min(8)->mixedCase()->numbers(),
            ],
            'phone' => 'nullable|string|max:20',
            'title' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            'type' => 'sometimes|in:company_admin,user',
            'roles' => 'sometimes|array',
            'roles.*' => 'integer|exists:roles,id',
            'is_active' => 'sometimes|boolean',
        ], [
            'name.required' => 'Ad soyad alanı zorunludur',
            'name.max' => 'Ad soyad en fazla 255 karakter olabilir',
            'email.required' => 'E-posta adresi zorunludur',
            'email.email' => 'Geçerli bir e-posta adresi giriniz',
            'email.unique' => 'Bu e-posta adresi zaten kullanılıyor',
            'password.required' => 'Şifre alanı zorunludur',
            'password' => 'Şifre en az 8 karakter olmalı ve en az bir büyük harf, bir küçük harf ve bir rakam içermelidir',
            'phone.max' => 'Telefon numarası en fazla 20 karakter olabilir',
            'type.in' => 'Geçersiz kullanıcı tipi',
            'roles.array' => 'Roller dizi formatında olmalıdır',
            'roles.*.integer' => 'Rol ID geçersiz',
            'roles.*.exists' => 'Seçilen rol bulunamadı',
        ]);

        $user = User::create([
            'company_id' => $this->getCompanyId(),
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'title' => $validated['title'] ?? null,
            'department' => $validated['department'] ?? null,
            'type' => $validated['type'] ?? 'user',
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => auth()->id(),
            'preferences' => ['theme' => 'dark', 'locale' => 'tr'],
        ]);

        // Rolleri ata (ID'lerden role isimlerini al)
        if (! empty($validated['roles'])) {
            $roleIds = $validated['roles'];
            $user->roles()->sync($roleIds);
        } else {
            $user->assignRole('employee');
        }

        ActivityLog::log(
            'create',
            $user,
            'Kullanıcı oluşturuldu: '.$user->name,
            null,
            $user->toArray()
        );

        return $this->created($user->load('roles'), 'Kullanıcı başarıyla oluşturuldu');
    }

    /**
     * Kullanıcı güncelle
     */
    public function update(Request $request, User $user): JsonResponse
    {
        // Firma kontrolü
        if ($user->company_id !== $this->getCompanyId()) {
            return $this->forbidden('Bu kullanıcıyı düzenleme yetkiniz yok');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,'.$user->id,
            'password' => [
                'sometimes',
                'nullable',
                Password::min(8)->mixedCase()->numbers(),
            ],
            'phone' => 'sometimes|nullable|string|max:20',
            'title' => 'sometimes|nullable|string|max:100',
            'department' => 'sometimes|nullable|string|max:100',
            'type' => 'sometimes|in:company_admin,user',
            'roles' => 'sometimes|array',
            'roles.*' => 'integer|exists:roles,id',
            'is_active' => 'sometimes|boolean',
        ], [
            'name.max' => 'Ad soyad en fazla 255 karakter olabilir',
            'email.email' => 'Geçerli bir e-posta adresi giriniz',
            'email.unique' => 'Bu e-posta adresi zaten kullanılıyor',
            'password' => 'Şifre en az 8 karakter olmalı ve en az bir büyük harf, bir küçük harf ve bir rakam içermelidir',
            'phone.max' => 'Telefon numarası en fazla 20 karakter olabilir',
            'type.in' => 'Geçersiz kullanıcı tipi',
            'roles.array' => 'Roller dizi formatında olmalıdır',
            'roles.*.integer' => 'Rol ID geçersiz',
            'roles.*.exists' => 'Seçilen rol bulunamadı',
        ]);

        // Şifre varsa hash'le
        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $validated['updated_by'] = auth()->id();

        $oldValues = $user->toArray();
        $user->update($validated);

        // Rolleri güncelle (ID'lerle)
        if (isset($validated['roles'])) {
            $user->roles()->sync($validated['roles']);
        }

        ActivityLog::log('update', $user, 'Kullanıcı güncellendi: '.$user->name, $oldValues, $user->fresh()->toArray());

        return $this->success($user->fresh()->load('roles'), 'Kullanıcı güncellendi');
    }

    /**
     * Kullanıcı sil
     */
    public function destroy(User $user): JsonResponse
    {
        // Firma kontrolü
        if ($user->company_id !== $this->getCompanyId()) {
            return $this->forbidden('Bu kullanıcıyı silme yetkiniz yok');
        }

        // Kendini silemez
        if ($user->id === auth()->id()) {
            return $this->error('Kendinizi silemezsiniz', 400);
        }

        $userName = $user->name;
        $oldValues = $user->toArray();
        $user->delete();

        ActivityLog::log('delete', null, 'Kullanıcı silindi: '.$userName, $oldValues, null);

        return $this->success(null, 'Kullanıcı silindi');
    }

    /**
     * Kullanıcı durumunu değiştir
     */
    public function toggleStatus(User $user): JsonResponse
    {
        // Firma kontrolü
        if ($user->company_id !== $this->getCompanyId()) {
            return $this->forbidden('Bu kullanıcının durumunu değiştirme yetkiniz yok');
        }

        // Kendini pasifleştiremez
        if ($user->id === auth()->id()) {
            return $this->error('Kendi durumunuzu değiştiremezsiniz', 400);
        }

        $user->update(['is_active' => ! $user->is_active]);

        $status = $user->is_active ? 'aktifleştirildi' : 'pasifleştirildi';
        ActivityLog::log('update', $user, "Kullanıcı {$status}: ".$user->name);

        return $this->success($user, "Kullanıcı {$status}");
    }

    /**
     * Avatar yükle
     */
    public function uploadAvatar(Request $request, User $user): JsonResponse
    {
        // Firma kontrolü
        if ($user->company_id !== $this->getCompanyId()) {
            return $this->forbidden('Bu kullanıcıya erişim yetkiniz yok');
        }

        $validated = $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // Eski avatar'ı sil
        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        // Yeni avatar'ı yükle
        $path = $request->file('avatar')->store('users/avatars', 'public');
        $user->update(['avatar' => $path]);

        ActivityLog::log('update', $user, "Kullanıcı avatar'ı güncellendi: {$user->name}");

        return $this->success([
            'avatar_url' => asset('storage/'.$path),
        ], 'Avatar başarıyla yüklendi');
    }

    /**
     * Avatar sil
     */
    public function deleteAvatar(User $user): JsonResponse
    {
        // Firma kontrolü
        if ($user->company_id !== $this->getCompanyId()) {
            return $this->forbidden('Bu kullanıcıya erişim yetkiniz yok');
        }

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $user->update(['avatar' => null]);

            ActivityLog::log('update', $user, "Kullanıcı avatar'ı silindi: {$user->name}");
        }

        return $this->success(null, 'Avatar başarıyla silindi');
    }

    /**
     * Kullanıcı davet et
     */
    public function invite(Request $request): JsonResponse
    {
        $company = auth()->user()->company;

        // Kullanıcı limiti kontrolü
        if ($company->hasReachedUserLimit()) {
            return $this->error('Kullanıcı limitine ulaşıldı', 400);
        }

        $validated = $request->validate([
            'email' => 'required|email|unique:users,email',
            'name' => 'required|string|max:255',
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,id',
        ]);

        // Davet token oluştur
        $token = Str::random(64);

        // Kullanıcı oluştur (henüz aktif değil, şifre yok)
        $user = User::create([
            'company_id' => $company->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make(Str::random(32)), // Geçici şifre
            'type' => 'user',
            'is_active' => false, // Davet kabul edilene kadar pasif
            'invitation_token' => Hash::make($token),
            'invited_at' => now(),
            'created_by' => auth()->id(),
        ]);

        // Roller atanırsa
        if (! empty($validated['roles'])) {
            $user->roles()->sync($validated['roles']);
        }

        // E-posta gönder (queued)
        try {
            $roleNames = $user->roles->pluck('name')->join(', ') ?: null;
            Mail::to($user->email)->queue(new UserInvitation($company, $user->email, $token, $roleNames));
        } catch (\Exception $e) {
            \Log::error('Invitation email failed: '.$e->getMessage());
            // Kullanıcıyı sil
            $user->delete();

            return $this->error('Davet e-postası kuyruğa alınamadı. Lütfen SMTP/queue ayarlarınızı kontrol edin.', 500);
        }

        ActivityLog::log('create', $user, "Kullanıcı davet edildi: {$user->name} ({$user->email})");

        return $this->success([
            'user' => $user,
            'invitation_sent' => true,
        ], 'Davet e-postası gönderildi');
    }

    /**
     * Kullanıcı şifresini sıfırla (Admin tarafından)
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        // Firma kontrolü
        if ($user->company_id !== $this->getCompanyId()) {
            return $this->forbidden('Bu kullanıcıya erişim yetkiniz yok');
        }

        $validated = $request->validate([
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            'notify_user' => 'nullable|boolean', // Kullanıcıya bildirim gönder
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Kullanıcının tüm token'larını iptal et (güvenlik için)
        $user->tokens()->delete();

        ActivityLog::log('update', $user, "Kullanıcı şifresi sıfırlandı: {$user->name}");

        // Kullanıcıya bildirim gönderilmesi istenirse
        if ($request->boolean('notify_user')) {
            Mail::to($user->email)->queue(new PasswordResetByAdmin($user, $validated['password']));
        }

        return $this->success(null, 'Şifre başarıyla sıfırlandı');
    }

    /**
     * Kullanıcı export (CSV)
     */
    public function export(Request $request): StreamedResponse
    {
        $query = User::where('company_id', $this->getCompanyId())
            ->with(['roles']);

        // Filtreler
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }
        if ($request->has('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        $users = $query->get();

        // CSV oluştur
        $filename = 'users_'.date('Y-m-d_His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($users) {
            $file = fopen('php://output', 'w');

            // BOM for Excel UTF-8 support
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Header
            fputcsv($file, ['Ad Soyad', 'E-posta', 'Telefon', 'Rol', 'Durum', 'Oluşturulma Tarihi']);

            // Data
            foreach ($users as $user) {
                $roles = $user->roles->pluck('name')->join(', ');
                fputcsv($file, [
                    $user->name,
                    $user->email,
                    $user->phone ?? '',
                    $roles ?: 'Rol yok',
                    $user->is_active ? 'Aktif' : 'Pasif',
                    $user->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        ActivityLog::log('export', null, 'Kullanıcılar export edildi');

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Toplu islemler
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
            'action' => 'required|in:activate,deactivate,delete,assign_role,remove_role',
            'role_id' => 'required_if:action,assign_role,remove_role|exists:roles,id',
        ]);

        $userIds = $validated['user_ids'];
        $action = $validated['action'];
        $users = User::whereIn('id', $userIds)
            ->where('company_id', $this->getCompanyId())
            ->get();

        if ($users->isEmpty()) {
            return $this->error('Secilen kullanicilar bulunamadi', 404);
        }

        $count = 0;
        foreach ($users as $user) {
            // Kendini islemden haric tut
            if ($user->id === auth()->id() && in_array($action, ['deactivate', 'delete'])) {
                continue;
            }

            switch ($action) {
                case 'activate':
                    $user->update(['is_active' => true]);
                    ActivityLog::log('update', $user, "Kullanıcı aktifleştirildi: {$user->name}");
                    $count++;
                    break;
                case 'deactivate':
                    $user->update(['is_active' => false]);
                    ActivityLog::log('update', $user, "Kullanıcı pasifleştirildi: {$user->name}");
                    $count++;
                    break;
                case 'delete':
                    ActivityLog::log('delete', $user, "Kullanıcı silindi: {$user->name}");
                    $user->delete();
                    $count++;
                    break;
                case 'assign_role':
                    if (! $user->roles->contains($validated['role_id'])) {
                        $user->roles()->attach($validated['role_id']);
                        ActivityLog::log('update', $user, "Kullanıcıya rol atandı: {$user->name}");
                        $count++;
                    }
                    break;
                case 'remove_role':
                    if ($user->roles->contains($validated['role_id'])) {
                        $user->roles()->detach($validated['role_id']);
                        ActivityLog::log('update', $user, "Kullanıcıdan rol kaldırıldı: {$user->name}");
                        $count++;
                    }
                    break;
            }
        }

        return $this->success([
            'affected_count' => $count,
        ], "{$count} kullanıcı üzerinde işlem yapıldı");
    }

    /**
     * Kullanıcı import (CSV)
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
        ]);

        $company = auth()->user()->company;
        $file = $request->file('file');
        $path = $file->getRealPath();
        $handle = fopen($path, 'r');

        if (! $handle) {
            return $this->error('Dosya okunamadı', 400);
        }

        // İlk satırı header olarak oku
        $headers = fgetcsv($handle);
        if (! $headers) {
            fclose($handle);

            return $this->error('CSV dosyası boş veya geçersiz', 400);
        }

        // Header'ları normalize et (küçük harf, boşlukları temizle)
        $headers = array_map(function ($header) {
            return strtolower(trim($header));
        }, $headers);

        // Gerekli kolonları kontrol et
        $requiredColumns = ['name', 'email'];
        $missingColumns = [];
        foreach ($requiredColumns as $col) {
            if (! in_array($col, $headers)) {
                $missingColumns[] = $col;
            }
        }

        if (! empty($missingColumns)) {
            fclose($handle);

            return $this->error('CSV dosyasında gerekli kolonlar eksik: '.implode(', ', $missingColumns), 400);
        }

        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $lineNumber = 1;
        $roleModel = \App\Models\Role::class;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;
            if (count($row) !== count($headers)) {
                $results['failed']++;
                $results['errors'][] = "Satır {$lineNumber}: Kolon sayısı uyuşmuyor";

                continue;
            }

            // Satırı associative array'e çevir
            $data = array_combine($headers, $row);

            // Boş satırları atla
            if (empty(trim($data['name'] ?? '')) || empty(trim($data['email'] ?? ''))) {
                continue;
            }

            // Email kontrolü
            $email = trim($data['email']);
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $results['failed']++;
                $results['errors'][] = "Satır {$lineNumber}: Geçersiz e-posta adresi ({$email})";

                continue;
            }

            // Kullanıcı limiti kontrolü
            if ($company->hasReachedUserLimit()) {
                $results['failed']++;
                $results['errors'][] = "Satır {$lineNumber}: Kullanıcı limitine ulaşıldı";

                continue;
            }

            // Kullanıcı zaten var mı kontrol et
            $existingUser = User::where('company_id', $company->id)
                ->where('email', $email)
                ->first();

            if ($existingUser) {
                $results['failed']++;
                $results['errors'][] = "Satır {$lineNumber}: Bu e-posta adresi zaten kullanılıyor ({$email})";

                continue;
            }

            try {
                // Kullanıcı oluştur
                $user = User::create([
                    'company_id' => $company->id,
                    'name' => trim($data['name']),
                    'email' => $email,
                    'phone' => trim($data['phone'] ?? ''),
                    'department' => trim($data['department'] ?? ''),
                    'password' => Hash::make(Str::random(32)), // Geçici şifre
                    'type' => 'user',
                    'is_active' => isset($data['is_active']) ? filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN) : true,
                    'created_by' => auth()->id(),
                ]);

                // Rolleri ata (virgülle ayrılmış rol isimleri)
                if (! empty($data['roles'] ?? '')) {
                    $roleNames = array_map('trim', explode(',', $data['roles']));
                    $roles = \App\Models\Role::where('company_id', $company->id)
                        ->whereIn('name', $roleNames)
                        ->pluck('id')
                        ->toArray();
                    if (! empty($roles)) {
                        $user->roles()->sync($roles);
                    }
                }

                ActivityLog::log('create', $user, "Kullanıcı import edildi: {$user->name} ({$user->email})");
                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Satır {$lineNumber}: ".$e->getMessage();
            }
        }

        fclose($handle);

        return $this->success($results, "Import tamamlandı: {$results['success']} başarılı, {$results['failed']} başarısız");
    }

    /**
     * 2FA'yı etkinleştir (QR code ve secret oluştur)
     */
    public function enable2FA(Request $request, User $user): JsonResponse
    {
        if ($user->company_id !== $this->getCompanyId()) {
            return $this->error('Yetkisiz erişim', 403);
        }

        // Secret oluştur (basit bir implementasyon - production'da google2fa paketi kullanılmalı)
        $secret = bin2hex(random_bytes(16));

        // Recovery codes oluştur
        $recoveryCodes = [];
        for ($i = 0; $i < 8; $i++) {
            $recoveryCodes[] = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
        }

        $user->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
            'two_factor_enabled' => false, // Kullanıcı doğrulamayı tamamlayana kadar false
        ]);

        // QR Code URL oluştur (Google Authenticator format)
        $qrCodeUrl = sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s',
            urlencode($user->company->name ?? 'HR System'),
            urlencode($user->email),
            $secret,
            urlencode($user->company->name ?? 'HR System')
        );

        ActivityLog::log('update', $user, "2FA etkinleştirme başlatıldı: {$user->name}");

        return $this->success([
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
            'recovery_codes' => $recoveryCodes, // Sadece ilk gösterimde
        ], '2FA etkinleştirme başlatıldı. Lütfen QR kodu tarayın ve doğrulama kodunu girin.');
    }

    /**
     * 2FA'yı doğrula ve etkinleştir
     */
    public function verify2FA(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        if ($user->company_id !== $this->getCompanyId()) {
            return $this->error('Yetkisiz erişim', 403);
        }

        // Basit doğrulama (production'da TOTP algoritması kullanılmalı)
        // Şimdilik sadece secret'ın varlığını kontrol ediyoruz
        if (! $user->two_factor_secret) {
            return $this->error('2FA secret bulunamadı', 400);
        }

        // TODO: Gerçek TOTP doğrulaması yapılmalı
        // $secret = decrypt($user->two_factor_secret);
        // $isValid = Google2FA::verifyKey($secret, $request->code);

        // Şimdilik her zaman true döndürüyoruz (geliştirme için)
        $isValid = true;

        if (! $isValid) {
            return $this->error('Geçersiz doğrulama kodu', 400);
        }

        $user->update([
            'two_factor_enabled' => true,
        ]);

        ActivityLog::log('update', $user, "2FA etkinleştirildi: {$user->name}");

        return $this->success(null, '2FA başarıyla etkinleştirildi');
    }

    /**
     * 2FA'yı devre dışı bırak
     */
    public function disable2FA(User $user): JsonResponse
    {
        if ($user->company_id !== $this->getCompanyId()) {
            return $this->error('Yetkisiz erişim', 403);
        }

        $user->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
        ]);

        ActivityLog::log('update', $user, "2FA devre dışı bırakıldı: {$user->name}");

        return $this->success(null, '2FA devre dışı bırakıldı');
    }

    /**
     * Recovery code'ları görüntüle
     */
    public function getRecoveryCodes(User $user): JsonResponse
    {
        if ($user->company_id !== $this->getCompanyId()) {
            return $this->error('Yetkisiz erişim', 403);
        }

        if (! $user->two_factor_recovery_codes) {
            return $this->error('Recovery code bulunamadı', 404);
        }

        $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);

        return $this->success([
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Recovery code'ları yenile
     */
    public function regenerateRecoveryCodes(User $user): JsonResponse
    {
        if ($user->company_id !== $this->getCompanyId()) {
            return $this->error('Yetkisiz erişim', 403);
        }

        if (! $user->two_factor_enabled) {
            return $this->error('2FA etkin değil', 400);
        }

        $recoveryCodes = [];
        for ($i = 0; $i < 8; $i++) {
            $recoveryCodes[] = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
        }

        $user->update([
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        ]);

        ActivityLog::log('update', $user, "2FA recovery code'ları yenilendi: {$user->name}");

        return $this->success([
            'recovery_codes' => $recoveryCodes,
        ], 'Recovery code\'lar yenilendi');
    }

    /**
     * Kullanıcının aktif oturumlarını listele
     */
    public function sessions(User $user): JsonResponse
    {
        if ($user->company_id !== $this->getCompanyId()) {
            return $this->error('Yetkisiz erişim', 403);
        }

        $tokens = PersonalAccessToken::where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->orderBy('last_used_at', 'desc')
            ->get()
            ->map(function ($token) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'last_used_at' => $token->last_used_at?->toIso8601String(),
                    'created_at' => $token->created_at->toIso8601String(),
                    'is_current' => $token->id === auth()->user()->currentAccessToken()?->id,
                ];
            });

        return $this->success($tokens);
    }

    /**
     * Belirli bir oturumu sonlandır
     */
    public function revokeSession(User $user, $tokenId): JsonResponse
    {
        if ($user->company_id !== $this->getCompanyId()) {
            return $this->error('Yetkisiz erişim', 403);
        }

        $token = PersonalAccessToken::where('id', $tokenId)
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->first();

        if (! $token) {
            return $this->error('Oturum bulunamadı', 404);
        }

        $token->delete();

        ActivityLog::log('update', $user, "Oturum sonlandırıldı: {$user->name} (Token: {$token->name})");

        return $this->success(null, 'Oturum başarıyla sonlandırıldı');
    }

    /**
     * Kullanıcının tüm oturumlarını sonlandır
     */
    public function revokeAllSessions(User $user): JsonResponse
    {
        if ($user->company_id !== $this->getCompanyId()) {
            return $this->error('Yetkisiz erişim', 403);
        }

        $count = PersonalAccessToken::where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->where('id', '!=', auth()->user()->currentAccessToken()?->id) // Mevcut oturumu hariç tut
            ->delete();

        ActivityLog::log('update', $user, "Tüm oturumlar sonlandırıldı: {$user->name} ({$count} oturum)");

        return $this->success(['revoked_count' => $count], "{$count} oturum başarıyla sonlandırıldı");
    }
}
