<?php

namespace App\Http\Controllers\Api\V1\Portal;

use App\Http\Controllers\Api\V1\BaseController;
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
        
        if (!$employee) {
            return $this->error('Personel kaydı bulunamadı', null, 404);
        }

        return $this->success([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar' => $user->avatar,
            ],
            'employee' => [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'title' => $employee->title,
                'position' => $employee->position,
                'department' => $employee->department?->name,
                'manager_name' => $employee->manager?->user?->name,
                
                // Kişisel bilgiler (düzenlenebilir)
                'birth_date' => $employee->birth_date?->format('Y-m-d'),
                'gender' => $employee->gender,
                'marital_status' => $employee->marital_status,
                'blood_type' => $employee->blood_type,
                
                // İletişim
                'personal_email' => $employee->personal_email,
                'personal_phone' => $employee->personal_phone,
                'address' => $employee->address,
                'city' => $employee->city,
                'district' => $employee->district,
                
                // Acil durum
                'emergency_contact_name' => $employee->emergency_contact_name,
                'emergency_contact_phone' => $employee->emergency_contact_phone,
                'emergency_contact_relation' => $employee->emergency_contact_relation,
                
                // İş bilgileri (sadece görüntüleme)
                'hire_date' => $employee->hire_date?->format('d.m.Y'),
                'contract_type' => $employee->contract_type,
                'work_type' => $employee->work_type,
                'status' => $employee->status,
            ],
        ]);
    }

    /**
     * Profil bilgilerini güncelle
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();
        
        if (!$employee) {
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
        ]);

        // Kullanıcı bilgilerini güncelle
        $userFields = ['name', 'phone'];
        $userUpdates = array_intersect_key($validated, array_flip($userFields));
        if (!empty($userUpdates)) {
            $user->update($userUpdates);
        }

        // Personel bilgilerini güncelle
        $employeeFields = [
            'personal_email', 'personal_phone', 'address', 'city', 'district',
            'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation'
        ];
        $employeeUpdates = array_intersect_key($validated, array_flip($employeeFields));
        if (!empty($employeeUpdates)) {
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

        if (!Hash::check($validated['current_password'], $user->password)) {
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

        $path = $request->file('avatar')->store('avatars/' . $user->company_id, 'public');
        $user->update(['avatar' => $path]);

        ActivityLog::log('update', $user, 'Avatar güncellendi');

        return $this->success(['avatar' => $path], 'Avatar başarıyla güncellendi');
    }
}

