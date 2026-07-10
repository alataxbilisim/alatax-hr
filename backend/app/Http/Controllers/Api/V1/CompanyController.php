<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class CompanyController extends BaseController
{
    /**
     * Firma bilgilerini getir
     */
    public function show(): JsonResponse
    {
        $company = auth()->user()->company;

        if (!$company) {
            return $this->notFound('Firma bulunamadı');
        }

        return $this->success([
            'id' => $company->id,
            'name' => $company->name,
            'slug' => $company->slug,
            'legal_name' => $company->legal_name,
            'tax_office' => $company->tax_office,
            'tax_number' => $company->tax_number,
            'phone' => $company->phone,
            'email' => $company->email,
            'website' => $company->website,
            'address' => $company->address,
            'city' => $company->city,
            'district' => $company->district,
            'postal_code' => $company->postal_code,
            'country' => $company->country,
            'sector' => $company->sector,
            'employee_count' => $company->employee_count,
            'logo' => $company->logo ? asset('storage/' . $company->logo) : null,
            'logo_path' => $company->logo,
            'settings' => $company->settings ?? [],
            'package_type' => $company->package_type,
            'user_limit' => $company->user_limit,
            'current_users' => $company->users()->where('is_active', true)->count(),
            'location_limit' => $company->location_limit,
            'current_locations' => $company->branches()->where('is_active', true)->count(),
            'employee_limit' => $company->employee_limit,
            'storage_limit' => $company->storage_limit,
            'license_start_date' => $company->license_start_date?->format('Y-m-d'),
            'license_end_date' => $company->license_end_date?->format('Y-m-d'),
            'status' => $company->status,
            'trial_ends_at' => $company->trial_ends_at?->format('Y-m-d'),
            'current_balance' => $company->current_balance,
            'balance_label' => $company->getBalanceLabel(),
        ]);
    }

    /**
     * Firma bilgilerini güncelle
     */
    public function update(Request $request): JsonResponse
    {
        $company = auth()->user()->company;

        if (!$company) {
            return $this->notFound('Firma bulunamadı');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'legal_name' => 'sometimes|nullable|string|max:255',
            'tax_office' => 'sometimes|nullable|string|max:100',
            'tax_number' => 'sometimes|nullable|string|max:20|regex:/^[0-9]{10}$/',
            'phone' => 'sometimes|nullable|string|max:20',
            'email' => 'sometimes|nullable|email|max:255',
            'website' => 'sometimes|nullable|url|max:255',
            'address' => 'sometimes|nullable|string|max:500',
            'city' => 'sometimes|nullable|string|max:100',
            'district' => 'sometimes|nullable|string|max:100',
            'postal_code' => 'sometimes|nullable|string|max:10',
            'country' => 'sometimes|nullable|string|max:100',
            'sector' => 'sometimes|nullable|string|max:100',
            'employee_count' => 'sometimes|nullable|string|max:50',
            'settings' => 'sometimes|array',
        ]);

        // Settings merge
        if (isset($validated['settings'])) {
            $validated['settings'] = array_merge(
                $company->settings ?? [],
                $validated['settings']
            );
        }

        $oldValues = $company->toArray();
        $company->update($validated);

        ActivityLog::log(
            'update',
            $company,
            'Firma bilgileri güncellendi',
            $oldValues,
            $company->fresh()->toArray()
        );

        return $this->success($company->fresh(), 'Firma bilgileri güncellendi');
    }

    /**
     * Logo yükle
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $company = auth()->user()->company;

        if (!$company) {
            return $this->notFound('Firma bulunamadı');
        }

        $request->validate([
            'logo' => 'required|image|mimes:jpg,jpeg,png|max:2048|dimensions:min_width=100,min_height=100',
        ]);

        try {
            // Eski logoyu sil
            if ($company->logo && Storage::disk('public')->exists($company->logo)) {
                Storage::disk('public')->delete($company->logo);
            }

            // Yeni logo yükle
            $file = $request->file('logo');
            $filename = 'companies/logos/' . $company->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            
            // Resmi optimize et ve kaydet
            $image = Image::make($file);
            
            // Maksimum boyut: 800x800
            $image->resize(800, 800, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            
            // Kalite ayarı
            $image->save(storage_path('app/public/' . $filename), 85);

            $oldValues = $company->toArray();
            $company->update(['logo' => $filename]);

            ActivityLog::log(
                'update',
                $company,
                'Firma logosu güncellendi',
                $oldValues,
                $company->fresh()->toArray()
            );

            return $this->success([
                'logo' => asset('storage/' . $filename),
                'logo_path' => $filename,
            ], 'Logo başarıyla yüklendi');
        } catch (\Exception $e) {
            return $this->serverError('Logo yüklenirken bir hata oluştu: ' . $e->getMessage());
        }
    }

    /**
     * Logo sil
     */
    public function deleteLogo(): JsonResponse
    {
        $company = auth()->user()->company;

        if (!$company) {
            return $this->notFound('Firma bulunamadı');
        }

        if (!$company->logo) {
            return $this->error('Logo bulunamadı', 404);
        }

        try {
            // Logoyu sil
            if (Storage::disk('public')->exists($company->logo)) {
                Storage::disk('public')->delete($company->logo);
            }

            $oldValues = $company->toArray();
            $company->update(['logo' => null]);

            ActivityLog::log(
                'update',
                $company,
                'Firma logosu silindi',
                $oldValues,
                $company->fresh()->toArray()
            );

            return $this->success(null, 'Logo başarıyla silindi');
        } catch (\Exception $e) {
            return $this->serverError('Logo silinirken bir hata oluştu: ' . $e->getMessage());
        }
    }

    /**
     * Firma modüllerini getir
     */
    public function modules(): JsonResponse
    {
        $company = auth()->user()->company;

        if (!$company) {
            return $this->notFound('Firma bulunamadı');
        }

        $modules = $company->modules()->get()->map(function ($module) {
            return [
                'id' => $module->id,
                'name' => $module->name,
                'slug' => $module->slug,
                'description' => $module->description,
                'icon' => $module->icon,
                'is_core' => $module->is_core,
                'is_active' => $module->pivot->is_active,
                'activated_at' => $module->pivot->activated_at,
                'expires_at' => $module->pivot->expires_at,
            ];
        });

        return $this->success($modules, 'Modüller listelendi');
    }
}
