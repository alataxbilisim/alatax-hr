<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Api\V1\BaseController;
use App\Http\Resources\EmployeeResource;
use App\Models\ActivityLog;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PortalProfileController extends BaseController
{
    /**
     * Profil bilgilerini getir
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)
            ->with(['department:id,name', 'manager:id,user_id', 'manager.user:id,name'])
            ->first();

        if (! $employee) {
            return $this->error('Personel kaydı bulunamadı', null, 404);
        }

        return $this->success([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar' => $user->avatar,
                'preferences' => $user->preferences ?? [],
            ],
            'employee' => new EmployeeResource($employee),
        ]);
    }

    /**
     * Profil bilgilerini güncelle
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (! $employee) {
            return $this->error('Personel kaydı bulunamadı', null, 404);
        }

        $validated = $request->validate([
            // Kullanıcı bilgileri
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|nullable|string|max:20',

            // Personel bilgileri (düzenlenebilir alanlar)
            'personal_email' => 'sometimes|nullable|email|max:255',
            'personal_phone' => 'sometimes|nullable|string|max:20',
            'address' => 'sometimes|nullable|string|max:500',
            'city' => 'sometimes|nullable|string|max:100',
            'district' => 'sometimes|nullable|string|max:100',

            // Acil durum
            'emergency_contact_name' => 'sometimes|nullable|string|max:255',
            'emergency_contact_phone' => 'sometimes|nullable|string|max:20',
            'emergency_contact_relation' => 'sometimes|nullable|string|max:100',

            // Bildirim tercihleri (4C-1)
            'preferences' => 'sometimes|array',
            'preferences.notifications' => 'sometimes|array',
            'preferences.notifications.email' => 'sometimes|array',
            'preferences.notifications.email.approvals' => 'sometimes|boolean',
            'preferences.notifications.email.requests' => 'sometimes|boolean',
            'preferences.notifications.email.tasks' => 'sometimes|boolean',
        ]);

        // Kullanıcı bilgilerini güncelle
        $userFields = ['name', 'phone'];
        $userUpdates = array_intersect_key($validated, array_flip($userFields));
        if (! empty($userUpdates)) {
            $user->update($userUpdates);
        }

        if (isset($validated['preferences'])) {
            $existing = $user->preferences ?? [];
            $incoming = $validated['preferences'];
            if (isset($incoming['notifications']) && is_array($incoming['notifications'])) {
                $existingNotif = is_array($existing['notifications'] ?? null) ? $existing['notifications'] : [];
                $incomingNotif = $incoming['notifications'];
                if (isset($incomingNotif['email']) && is_array($incomingNotif['email'])) {
                    $existingEmail = is_array($existingNotif['email'] ?? null) ? $existingNotif['email'] : [];
                    $incomingNotif['email'] = array_merge($existingEmail, $incomingNotif['email']);
                }
                $incoming['notifications'] = array_merge($existingNotif, $incomingNotif);
            }
            $user->update(['preferences' => array_merge($existing, $incoming)]);
        }

        // Personel bilgilerini güncelle
        $employeeFields = [
            'personal_email', 'personal_phone', 'address', 'city', 'district',
            'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation',
        ];
        $employeeUpdates = array_intersect_key($validated, array_flip($employeeFields));
        if (! empty($employeeUpdates)) {
            $employee->update($employeeUpdates);
        }

        ActivityLog::log('update', $employee, 'Profil güncellendi');

        return $this->success(null, 'Profil başarıyla güncellendi');
    }

    /**
     * Şifre güncelle
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return $this->error('Mevcut şifre hatalı', ['current_password' => ['Mevcut şifre hatalı']], 422);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        ActivityLog::log('password_change', $user, 'Portal şifresi değiştirildi');

        return $this->success(null, 'Şifre başarıyla güncellendi');
    }

    /**
     * Avatar güncelle
     */
    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|max:2048', // Max 2MB
        ]);

        $user = $request->user();

        // Eski avatarı sil
        if ($user->avatar) {
            \Storage::disk('public')->delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars/'.$user->company_id, 'public');
        $user->update(['avatar' => $path]);

        ActivityLog::log('update', $user, 'Avatar güncellendi');

        return $this->success(['avatar' => $path], 'Avatar başarıyla güncellendi');
    }
}
