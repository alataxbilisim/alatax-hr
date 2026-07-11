<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\CompanyLedger;
use App\Models\LicensePackage;
use App\Models\Module;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CompanyController extends BaseController
{
    /**
     * Firma listesi
     */
    public function index(Request $request): JsonResponse
    {
        $query = Company::withCount('users')
            ->with([
                'modules' => function ($q) {
                    $q->wherePivot('is_active', true);
                },
                'licensePackage:id,name,slug',
            ])
            ->select([
                'companies.*',
                // current_balance zaten fillable'da, otomatik gelir
            ]);

        // Arama
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('tax_number', 'like', "%{$search}%");
            });
        }

        // Durum filtresi
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Paket filtresi
        if ($request->has('package_type')) {
            $query->where('package_type', $request->package_type);
        }

        // Sıralama
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $companies = $query->paginate($request->get('per_page', 15));

        return $this->paginated($companies, 'Firmalar listelendi');
    }

    /**
     * Firma detay
     */
    public function show(Company $company): JsonResponse
    {
        $company->load([
            'users' => function ($q) {
                $q->select('id', 'company_id', 'name', 'email', 'type', 'is_active', 'last_login_at');
            },
            'modules',
            'licensePackage',
            'ledger' => function ($q) {
                $q->latest()->limit(10);
            },
        ]);

        $company->loadCount('users');

        return $this->success($company);
    }

    /**
     * Firma oluştur (Admin kullanıcısı ile birlikte)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Firma bilgileri
            'name' => 'required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'tax_office' => 'nullable|string|max:100',
            'tax_number' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'sector' => 'nullable|string|max:100',
            'package_type' => 'nullable|in:starter,professional,enterprise',
            'license_package_id' => 'nullable|exists:license_packages,id',
            'user_limit' => 'nullable|integer|min:1',
            'status' => 'required|in:active,suspended,cancelled,trial',
            'license_start_date' => 'nullable|date',
            'license_end_date' => 'nullable|date|after:license_start_date',
            'trial_ends_at' => 'nullable|date',
            // Admin kullanıcı bilgileri
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|max:255|unique:users,email',
            'admin_password' => 'required|string|min:8',
            'admin_phone' => 'nullable|string|max:20',
        ]);

        return DB::transaction(function () use ($validated) {
            // Firma oluştur
            $companyData = collect($validated)->except([
                'admin_name', 'admin_email', 'admin_password', 'admin_phone',
            ])->toArray();

            // Slug oluştur
            if (empty($companyData['slug'])) {
                $companyData['slug'] = Str::slug($validated['name']);
            }

            // License package varsa limitleri al
            $package = null;
            if (! empty($validated['license_package_id'])) {
                $package = LicensePackage::find($validated['license_package_id']);
                if ($package) {
                    $companyData['user_limit'] = $package->user_limit ?? $validated['user_limit'] ?? 5;
                    $companyData['employee_limit'] = $package->employee_limit ?? 50;
                    $companyData['location_limit'] = $package->location_limit ?? 1;
                    // storage_limit GB cinsinden, Company modelinde bytes'a çevrilir
                    $companyData['storage_limit'] = ($package->storage_limit_gb ?? 10) * 1073741824; // GB to bytes
                }
            } else {
                // Default değerler
                $companyData['user_limit'] = $validated['user_limit'] ?? 5;
                $companyData['employee_limit'] = 50;
                $companyData['location_limit'] = 1;
                $companyData['storage_limit'] = 10 * 1073741824; // 10 GB to bytes
            }

            $company = Company::create($companyData);

            // Core modülleri önce aktifle
            $coreModules = Module::where('is_core', true)->pluck('id')->toArray();
            $syncData = [];
            $activatedDate = now()->toDateString(); // date tipinde alan için

            foreach ($coreModules as $moduleId) {
                $syncData[$moduleId] = [
                    'is_active' => true,
                    'activated_at' => $activatedDate,
                ];
            }

            // License package varsa modülleri ekle (core modüller hariç)
            if ($package) {
                $packageModules = $package->modules()
                    ->wherePivot('is_included', true)
                    ->pluck('id')
                    ->toArray();

                foreach ($packageModules as $moduleId) {
                    // Core modül değilse ekle
                    if (! in_array($moduleId, $coreModules)) {
                        $syncData[$moduleId] = [
                            'is_active' => true,
                            'activated_at' => $activatedDate,
                        ];
                    }
                }
            }

            // Tüm modülleri sync et
            if (! empty($syncData)) {
                $company->modules()->sync($syncData);
            }

            // Admin kullanıcısını oluştur
            $admin = User::create([
                'company_id' => $company->id,
                'name' => $validated['admin_name'],
                'email' => $validated['admin_email'],
                'password' => Hash::make($validated['admin_password']),
                'phone' => $validated['admin_phone'] ?? null,
                'type' => 'company_admin',
                'is_active' => true,
            ]);

            // Spatie admin rolü (sanctum) — Gate bypass kalkınca yetki buradan gelir
            $role = Role::firstOrCreate(
                ['name' => 'admin', 'guard_name' => 'sanctum']
            );
            if ($role->data_scope === null) {
                $role->forceFill(['data_scope' => 'company'])->save();
            }
            $admin->assignRole($role);

            ActivityLog::log(
                'role_sync',
                $admin,
                'Firma admin rolü atandı: '.$admin->email,
                null,
                ['roles' => ['admin']]
            );

            return $this->created([
                'company' => $company->load('modules'),
                'admin' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'type' => $admin->type,
                ],
            ], 'Firma ve admin kullanıcısı oluşturuldu');
        });
    }

    /**
     * Firma güncelle
     */
    public function update(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'legal_name' => 'sometimes|nullable|string|max:255',
            'tax_office' => 'sometimes|nullable|string|max:100',
            'tax_number' => 'sometimes|nullable|string|max:20',
            'phone' => 'sometimes|nullable|string|max:20',
            'email' => 'sometimes|nullable|email|max:255',
            'website' => 'sometimes|nullable|url|max:255',
            'address' => 'sometimes|nullable|string|max:500',
            'city' => 'sometimes|nullable|string|max:100',
            'country' => 'sometimes|nullable|string|max:100',
            'district' => 'sometimes|nullable|string|max:100',
            'sector' => 'sometimes|nullable|string|max:100',
            'employee_count' => 'sometimes|nullable|string|max:50',
            'package_type' => 'sometimes|in:starter,professional,enterprise',
            'license_package_id' => 'sometimes|nullable|exists:license_packages,id',
            'user_limit' => 'sometimes|integer|min:1',
            'employee_limit' => 'sometimes|integer|min:1',
            'location_limit' => 'sometimes|integer|min:1',
            'location_count' => 'sometimes|integer|min:0',
            'storage_limit' => 'sometimes|integer|min:0',
            'status' => 'sometimes|in:active,suspended,cancelled,trial',
            'license_start_date' => 'sometimes|nullable|date',
            'license_end_date' => 'sometimes|nullable|date',
            'trial_ends_at' => 'sometimes|nullable|date',
            'duration_months' => 'sometimes|nullable|integer|min:1|max:60',
        ]);

        $oldValues = $company->toArray();

        // Eğer license_package_id değiştiyse, assignPackage() metodunu kullan (modülleri sync eder)
        // null değeri de değişiklik sayılır (paketi kaldırma)
        if (array_key_exists('license_package_id', $validated) && $validated['license_package_id'] != $company->license_package_id) {
            if ($validated['license_package_id'] === null) {
                // Paketi kaldır
                $company->update([
                    'license_package_id' => null,
                    'package_type' => 'starter', // Default'a dön
                ]);
            } else {
                // Yeni paket ata
                $package = LicensePackage::findOrFail($validated['license_package_id']);
                $durationMonths = $validated['duration_months'] ?? $package->duration_months ?? 12;
                $company->assignPackage($package, $durationMonths);
            }
        } elseif (isset($validated['package_type']) && $validated['package_type'] != $company->package_type) {
            // package_type değiştiyse ama license_package_id değişmediyse, package_type slug'ına göre paketi bul ve modülleri sync et
            $package = LicensePackage::where('slug', $validated['package_type'])->first();
            if ($package) {
                // Paketteki modülleri firmaya ata
                $moduleIds = $package->includedModules()->pluck('modules.id')->toArray();
                $syncData = [];
                foreach ($moduleIds as $moduleId) {
                    $syncData[$moduleId] = ['is_active' => true, 'activated_at' => now()->toDateString()];
                }
                $company->modules()->sync($syncData);
                // package_type'ı güncelle
                $company->update(['package_type' => $validated['package_type']]);
            } else {
                // Paket bulunamadı, sadece package_type'ı güncelle
                $company->update($validated);
            }
        } else {
            // Normal update (paket değişikliği yok)
            $company->update($validated);
        }

        return $this->success($company->fresh(), 'Firma güncellendi');
    }

    /**
     * Firma sil
     */
    public function destroy(Company $company): JsonResponse
    {
        // Kullanıcısı olan firma silinemez
        if ($company->users()->count() > 0) {
            return $this->error('Bu firmaya ait kullanıcılar var. Önce kullanıcıları silmelisiniz.', 400);
        }

        $companyName = $company->name;
        $company->delete();

        return $this->success(null, 'Firma silindi');
    }

    /**
     * Firma durumunu değiştir
     */
    public function toggleStatus(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:active,suspended,cancelled,trial',
        ]);

        $oldStatus = $company->status;
        Company::withoutAuditing(fn () => $company->update(['status' => $validated['status']]));

        ActivityLog::log('status_change', $company, "Firma durumu değiştirildi: {$oldStatus} -> {$validated['status']}");

        return $this->success($company, 'Firma durumu güncellendi');
    }

    /**
     * Firma modüllerini güncelle
     */
    public function syncModules(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'modules' => 'required|array',
            'modules.*.id' => 'required|exists:modules,id',
            'modules.*.is_active' => 'required|boolean',
            'modules.*.expires_at' => 'nullable|date',
        ]);

        foreach ($validated['modules'] as $moduleData) {
            $module = Module::find($moduleData['id']);

            // Core modüller her zaman aktif
            if ($module->is_core) {
                continue;
            }

            $company->modules()->syncWithoutDetaching([
                $moduleData['id'] => [
                    'is_active' => $moduleData['is_active'],
                    'activated_at' => $moduleData['is_active'] ? now() : null,
                    'expires_at' => $moduleData['expires_at'] ?? null,
                ],
            ]);
        }

        ActivityLog::log('modules_sync', $company, 'Firma modülleri güncellendi');

        return $this->success(
            $company->fresh()->load('modules'),
            'Modüller güncellendi'
        );
    }

    /**
     * Firmaya lisans paketi ata
     */
    public function assignPackage(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'license_package_id' => 'required|exists:license_packages,id',
            'duration_months' => 'nullable|integer|min:1|max:60',
            'add_to_ledger' => 'boolean',
            'custom_price' => 'nullable|numeric|min:0',
        ]);

        $package = LicensePackage::findOrFail($validated['license_package_id']);
        $durationMonths = $validated['duration_months'] ?? $package->duration_months;

        return DB::transaction(function () use ($company, $package, $durationMonths, $validated) {
            // Paketi ata
            $company->assignPackage($package, $durationMonths);

            // Cariye borç ekle
            if ($validated['add_to_ledger'] ?? true) {
                $price = $validated['custom_price'] ?? ($durationMonths >= 12
                    ? $package->annual_price
                    : $package->base_price * $durationMonths);

                CompanyLedger::addTransaction(
                    $company->id,
                    CompanyLedger::TYPE_DEBIT,
                    $price,
                    "Lisans Satışı: {$package->name} - {$durationMonths} Ay",
                    CompanyLedger::REF_LICENSE,
                    $package->id
                );
            }

            ActivityLog::log('license_assign', $company, "Lisans paketi atandı: {$package->name}");

            $company->refresh();

            return $this->success([
                'company' => $company->load(['licensePackage', 'modules']),
                'new_balance' => $company->current_balance,
            ], 'Lisans paketi atandı');
        });
    }

    /**
     * Lisans süresini uzat
     */
    public function extendLicense(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'months' => 'required|integer|min:1|max:60',
            'add_to_ledger' => 'boolean',
            'amount' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:255',
        ]);

        return DB::transaction(function () use ($company, $validated) {
            $company->extendLicense($validated['months']);

            // Cariye borç ekle
            if (($validated['add_to_ledger'] ?? false) && ! empty($validated['amount'])) {
                CompanyLedger::addTransaction(
                    $company->id,
                    CompanyLedger::TYPE_DEBIT,
                    $validated['amount'],
                    $validated['description'] ?? "Lisans Uzatma: {$validated['months']} Ay",
                    CompanyLedger::REF_RENEWAL,
                    null
                );
            }

            ActivityLog::log('license_extend', $company, "Lisans süresi uzatıldı: {$validated['months']} ay");

            $company->refresh();

            return $this->success([
                'company' => $company,
                'new_license_end_date' => $company->license_end_date,
                'new_balance' => $company->current_balance,
            ], 'Lisans süresi uzatıldı');
        });
    }
}
