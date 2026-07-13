<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserType;
use App\Http\Resources\EmployeeResource;
use App\Mail\PasswordResetByAdmin;
use App\Mail\UserInvitation;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\InvitationService;
use App\Services\TwoFactorService;
use App\Support\PanelAccess;
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
    public function __construct(
        protected TwoFactorService $twoFactor,
        protected InvitationService $invitations,
    ) {}

    /**
     * Kullanıcı listesi
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::where('company_id', $this->getCompanyId())
            ->with(['roles', 'employee:id,user_id,employee_code']);

        // Karar B: yalnızca panel erişimli kullanıcılar (portal-only personel hariç)
        PanelAccess::constrainUsersQuery($query);

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
     * Panel rolü verilebilecek portal-only personeller (user + employee, panel erişimi yok).
     */
    public function portalCandidates(Request $request): JsonResponse
    {
        $query = User::where('company_id', $this->getCompanyId())
            ->with(['roles', 'employee']);

        PanelAccess::constrainPortalOnlyQuery($query);

        if ($request->filled('search')) {
            $search = (string) $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $query->orderBy('name');

        $users = $query->paginate($request->get('per_page', 20));

        return $this->paginated($users, 'Panel adayları listelendi');
    }

    /**
     * Mevcut personel kullanıcısına panel rolü ata (yeni user oluşturmaz).
     */
    public function grantPanelAccess(Request $request, User $user): JsonResponse
    {
        if ($user->company_id !== $this->getCompanyId()) {
            return $this->forbidden('Bu kullanıcıya erişim yetkiniz yok');
        }

        if (! $user->employee) {
            return $this->error('Panel erişimi yalnızca personel kaydı olan kullanıcılara verilebilir', 422);
        }

        if (PanelAccess::has($user)) {
            return $this->error('Kullanıcının zaten panel erişimi var', 422);
        }

        $validated = $request->validate([
            'role' => [
                'required',
                'string',
                'in:'.implode(',', PanelAccess::GRANTABLE_PANEL_ROLES),
            ],
        ], [
            'role.required' => 'Panel rolü seçilmelidir',
            'role.in' => 'Seçilen rol panele yükseltilmek için geçerli değil',
        ]);

        $roleName = $validated['role'];
        $oldRoles = $user->roles->pluck('name')->sort()->values()->all();

        // Portal erişimini koru: employee rolü kalsın, panel rolü eklensin
        if (! $user->hasRole('employee')) {
            $user->assignRole('employee');
        }
        $user->assignRole($roleName);
        $user->unsetRelation('roles');
        $user->load('roles');

        if (! PanelAccess::has($user)) {
            $user->removeRole($roleName);

            return $this->error('Atanan rol panel erişimi sağlamıyor', 422);
        }

        $newRoles = $user->roles->pluck('name')->sort()->values()->all();
        ActivityLog::log(
            'panel_access_grant',
            $user,
            "Personele panel erişimi verildi: {$user->name} ({$roleName})",
            ['roles' => $oldRoles],
            ['roles' => $newRoles]
        );

        return $this->success([
            'user' => $user->fresh()->load(['roles', 'employee']),
            'panel_access' => true,
        ], 'Panel erişimi verildi');
    }

    /**
     * Panel rollerini kaldır → yalnızca portal (employee) kalır.
     */
    public function revokePanelAccess(User $user): JsonResponse
    {
        if ($user->company_id !== $this->getCompanyId()) {
            return $this->forbidden('Bu kullanıcıya erişim yetkiniz yok');
        }

        if ($user->id === auth()->id()) {
            return $this->error('Kendi panel erişiminizi kaldıramazsınız', 422);
        }

        if ($user->type === UserType::CompanyAdmin || $user->type === UserType::SuperAdmin) {
            return $this->error('Firma/süper admin kullanıcılarının panel erişimi kaldırılamaz', 422);
        }

        if (! PanelAccess::has($user)) {
            return $this->error('Kullanıcının panel erişimi yok', 422);
        }

        if (! $user->employee) {
            return $this->error('Panel erişimi kaldırma yalnızca personel kullanıcıları için geçerlidir', 422);
        }

        $oldRoles = $user->roles->pluck('name')->sort()->values()->all();

        // Tüm roller çıkar, yalnızca employee bırak (portal)
        $user->syncRoles(['employee']);
        $user->unsetRelation('roles');
        $user->load('roles');

        if (PanelAccess::has($user)) {
            return $this->error('Panel erişimi kaldırılamadı; kullanıcıda hâlâ panel izinleri var', 422);
        }

        $newRoles = $user->roles->pluck('name')->sort()->values()->all();
        ActivityLog::log(
            'panel_access_revoke',
            $user,
            "Personelin panel erişimi kaldırıldı: {$user->name}",
            ['roles' => $oldRoles],
            ['roles' => $newRoles]
        );

        return $this->success([
            'user' => $user->fresh()->load(['roles', 'employee']),
            'panel_access' => false,
        ], 'Panel erişimi kaldırıldı');
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

        // Rolleri ata (ID'lerden)
        $assignedRoles = [];
        if (! empty($validated['roles'])) {
            $user->roles()->sync($validated['roles']);
            $assignedRoles = $user->fresh()->roles->pluck('name')->values()->all();
        } else {
            $user->assignRole('employee');
            $assignedRoles = ['employee'];
        }

        // Observer create log yazar; rol pivot'u özel log
        ActivityLog::log(
            'role_sync',
            $user,
            'Kullanıcı rolleri atandı: '.$user->name,
            null,
            ['roles' => $assignedRoles]
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

        $oldRoles = $user->roles->pluck('name')->sort()->values()->all();
        $user->update($validated);

        // Rolleri güncelle (ID'lerle) — pivot; observer yakalamaz
        if (isset($validated['roles'])) {
            $user->roles()->sync($validated['roles']);
            $newRoles = $user->fresh()->roles->pluck('name')->sort()->values()->all();
            if ($oldRoles !== $newRoles) {
                ActivityLog::log(
                    'role_sync',
                    $user,
                    'Kullanıcı rolleri güncellendi: '.$user->name,
                    ['roles' => $oldRoles],
                    ['roles' => $newRoles]
                );
            }
        }

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
        $user->delete();

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

        User::withoutAuditing(function () use ($user) {
            $user->update(['is_active' => ! $user->is_active]);
        });

        $status = $user->is_active ? 'aktifleştirildi' : 'pasifleştirildi';
        ActivityLog::log('status_change', $user, "Kullanıcı {$status}: ".$user->name);

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
        User::withoutAuditing(function () use ($user, $path) {
            $user->update(['avatar' => $path]);
        });

        ActivityLog::log('avatar_update', $user, "Kullanıcı avatar'ı güncellendi: {$user->name}");

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
            User::withoutAuditing(fn () => $user->update(['avatar' => null]));

            ActivityLog::log('avatar_update', $user, "Kullanıcı avatar'ı silindi: {$user->name}");
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
        $issue = $this->invitations->issue();
        $token = $issue['plain'];

        // Kullanıcı oluştur (henüz aktif değil, şifre yok) — invite özel log; CRUD observer kapalı
        $user = User::withoutAuditing(function () use ($company, $validated, $issue) {
            return User::create([
                'company_id' => $company->id,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make(Str::random(32)), // Geçici şifre (giriş kapalı)
                'type' => 'user',
                'is_active' => false, // Davet kabul edilene kadar pasif
                'must_change_password' => false,
                'invitation_token' => $issue['hash'],
                'invited_at' => $issue['invited_at'],
                'created_by' => auth()->id(),
            ]);
        });

        // Roller atanırsa
        if (! empty($validated['roles'])) {
            $user->roles()->sync($validated['roles']);
            ActivityLog::log(
                'role_sync',
                $user,
                'Davet kullanıcısına rol atandı: '.$user->name,
                null,
                ['roles' => $user->roles->pluck('name')->values()->all()]
            );
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

        ActivityLog::log('invite', $user, "Kullanıcı davet edildi: {$user->name} ({$user->email})");

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

        User::withoutAuditing(fn () => $user->update([
            'password' => Hash::make($validated['password']),
        ]));

        // Kullanıcının tüm token'larını iptal et (güvenlik için)
        $user->tokens()->delete();

        ActivityLog::log('password_reset', $user, "Kullanıcı şifresi sıfırlandı: {$user->name}");

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
                    User::withoutAuditing(fn () => $user->update(['is_active' => true]));
                    ActivityLog::log('status_change', $user, "Kullanıcı aktifleştirildi: {$user->name}");
                    $count++;
                    break;
                case 'deactivate':
                    User::withoutAuditing(fn () => $user->update(['is_active' => false]));
                    ActivityLog::log('status_change', $user, "Kullanıcı pasifleştirildi: {$user->name}");
                    $count++;
                    break;
                case 'delete':
                    $user->delete();
                    $count++;
                    break;
                case 'assign_role':
                    if (! $user->roles->contains($validated['role_id'])) {
                        $user->roles()->attach($validated['role_id']);
                        ActivityLog::log('role_sync', $user, "Kullanıcıya rol atandı: {$user->name}");
                        $count++;
                    }
                    break;
                case 'remove_role':
                    if ($user->roles->contains($validated['role_id'])) {
                        $user->roles()->detach($validated['role_id']);
                        ActivityLog::log('role_sync', $user, "Kullanıcıdan rol kaldırıldı: {$user->name}");
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
     * 2FA kurulum başlat (secret + QR + recovery — henüz aktif değil)
     */
    public function enable2FA(Request $request, User $user): JsonResponse
    {
        if ($user->company_id !== $this->getCompanyId()) {
            return $this->error('Yetkisiz erişim', 403);
        }

        if ($user->two_factor_enabled) {
            return $this->error('2FA zaten etkin. Önce devre dışı bırakın.', 400);
        }

        $secret = $this->twoFactor->generateSecret();
        $plainRecovery = $this->twoFactor->generateRecoveryCodes();
        $hashedRecovery = $this->twoFactor->hashRecoveryCodes($plainRecovery);
        $otpAuthUrl = $this->twoFactor->getOtpAuthUrl($user, $secret);

        User::withoutAuditing(fn () => $user->update([
            'two_factor_secret' => $this->twoFactor->encryptSecret($secret),
            'two_factor_recovery_codes' => $this->twoFactor->storeEncryptedRecoveryHashes($hashedRecovery),
            'two_factor_enabled' => false,
        ]));

        ActivityLog::log('two_factor_setup', $user, "2FA etkinleştirme başlatıldı: {$user->name}");

        return $this->success([
            'secret' => $secret,
            'qr_code_url' => $otpAuthUrl,
            'qr_code_svg' => $this->twoFactor->generateQrSvg($otpAuthUrl),
            'recovery_codes' => $plainRecovery,
            'two_factor_enabled' => false,
        ], '2FA etkinleştirme başlatıldı. Authenticator uygulamasına ekleyip doğrulama kodunu girin.');
    }

    /**
     * İlk TOTP doğrulaması → 2FA aktif
     */
    public function verify2FA(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        if ($user->company_id !== $this->getCompanyId()) {
            return $this->error('Yetkisiz erişim', 403);
        }

        if (! $user->two_factor_secret) {
            return $this->error('2FA secret bulunamadı. Önce enable çağırın.', 400);
        }

        if ($user->two_factor_enabled) {
            return $this->error('2FA zaten etkin', 400);
        }

        $secret = $this->twoFactor->decryptSecret($user->two_factor_secret);

        if (! $this->twoFactor->verifyTotp($secret, $request->code)) {
            ActivityLog::log(
                'two_factor_failed',
                $user,
                "2FA etkinleştirme doğrulaması başarısız: {$user->name}",
                null,
                null,
                false,
                'Geçersiz TOTP'
            );

            return $this->error('Geçersiz doğrulama kodu', 400);
        }

        User::withoutAuditing(fn () => $user->update([
            'two_factor_enabled' => true,
        ]));

        ActivityLog::log('two_factor_enable', $user, "2FA etkinleştirildi: {$user->name}");

        return $this->success([
            'two_factor_enabled' => true,
        ], '2FA başarıyla etkinleştirildi');
    }

    /**
     * 2FA kapat — hedef kullanıcının TOTP kodu VEYA işlem yapanın şifresi
     */
    public function disable2FA(Request $request, User $user): JsonResponse
    {
        if ($user->company_id !== $this->getCompanyId()) {
            return $this->error('Yetkisiz erişim', 403);
        }

        $validated = $request->validate([
            'code' => 'required_without:password|nullable|string',
            'password' => 'required_without:code|nullable|string',
        ]);

        $actor = $request->user();
        $confirmed = false;

        if (! empty($validated['password'])) {
            $confirmed = Hash::check($validated['password'], $actor->password);
        } elseif (! empty($validated['code']) && $user->two_factor_secret) {
            $secret = $this->twoFactor->decryptSecret($user->two_factor_secret);
            $confirmed = $this->twoFactor->verifyTotp($secret, $validated['code']);
        }

        if (! $confirmed) {
            ActivityLog::log(
                'two_factor_failed',
                $user,
                "2FA kapatma doğrulaması başarısız: {$user->name}",
                null,
                null,
                false,
                'Geçersiz kod veya şifre'
            );

            return $this->error('Geçersiz doğrulama kodu veya şifre', 403);
        }

        User::withoutAuditing(fn () => $user->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
        ]));

        ActivityLog::log('two_factor_disable', $user, "2FA devre dışı bırakıldı: {$user->name}");

        return $this->success(null, '2FA devre dışı bırakıldı');
    }

    /**
     * Recovery kodları düz metin olarak döndürülemez (hash'li).
     */
    public function getRecoveryCodes(User $user): JsonResponse
    {
        if ($user->company_id !== $this->getCompanyId()) {
            return $this->error('Yetkisiz erişim', 403);
        }

        if (! $user->two_factor_enabled) {
            return $this->error('2FA etkin değil', 400);
        }

        $hashes = $this->twoFactor->decryptRecoveryHashes($user->two_factor_recovery_codes);

        return $this->success([
            'remaining_count' => $hashes ? count($hashes) : 0,
            'message' => 'Recovery kodları yalnızca üretim anında gösterilir. Yenilemek için regenerate kullanın.',
        ]);
    }

    /**
     * Recovery code'ları yenile (düz metin bir kez döner; hash saklanır)
     */
    public function regenerateRecoveryCodes(Request $request, User $user): JsonResponse
    {
        if ($user->company_id !== $this->getCompanyId()) {
            return $this->error('Yetkisiz erişim', 403);
        }

        if (! $user->two_factor_enabled || ! $user->two_factor_secret) {
            return $this->error('2FA etkin değil', 400);
        }

        $validated = $request->validate([
            'code' => 'required_without:password|nullable|string',
            'password' => 'required_without:code|nullable|string',
        ]);

        $actor = $request->user();
        $confirmed = false;

        if (! empty($validated['password'])) {
            $confirmed = Hash::check($validated['password'], $actor->password);
        } elseif (! empty($validated['code'])) {
            $secret = $this->twoFactor->decryptSecret($user->two_factor_secret);
            $confirmed = $this->twoFactor->verifyTotp($secret, $validated['code']);
        }

        if (! $confirmed) {
            return $this->error('Geçersiz doğrulama kodu veya şifre', 403);
        }

        $plainRecovery = $this->twoFactor->generateRecoveryCodes();
        $hashedRecovery = $this->twoFactor->hashRecoveryCodes($plainRecovery);

        User::withoutAuditing(fn () => $user->update([
            'two_factor_recovery_codes' => $this->twoFactor->storeEncryptedRecoveryHashes($hashedRecovery),
        ]));

        ActivityLog::log('two_factor_recovery_regen', $user, "2FA recovery code'ları yenilendi: {$user->name}");

        return $this->success([
            'recovery_codes' => $plainRecovery,
        ], 'Recovery code\'lar yenilendi. Bu kodları güvenli yerde saklayın.');
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
