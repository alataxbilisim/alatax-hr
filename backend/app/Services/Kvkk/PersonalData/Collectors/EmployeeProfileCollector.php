<?php

namespace App\Services\Kvkk\PersonalData\Collectors;

use App\Models\Employee;
use App\Models\User;
use App\Services\Kvkk\PersonalData\AnonymizationHelper;
use App\Services\Kvkk\PersonalData\PersonalDataCollector;
use App\Services\Kvkk\PersonalData\ResolvesSubjectUser;
use Illuminate\Support\Facades\Storage;

/**
 * Profil imhası: kimlik alanları maskelenir; departman/pozisyon/cinsiyet/işe giriş yılı kalır.
 * FK bütünlüğü korunur — soft delete yok, hard delete yalnız açıkça 'delete'.
 */
final class EmployeeProfileCollector implements PersonalDataCollector
{
    use ResolvesSubjectUser;

    public function key(): string
    {
        return 'employee_profile';
    }

    public function labelKey(): string
    {
        return 'kvkk.collectors.employeeProfile';
    }

    public function collect(string $subjectType, int $subjectId, int $companyId): array
    {
        $userId = $this->resolveUserId($subjectType, $subjectId, $companyId);
        if (! $userId) {
            return [];
        }

        $user = User::query()->where('home_company_id', $companyId)->where('id', $userId)->first();
        $employee = Employee::query()->where('company_id', $companyId)->where('user_id', $userId)->first();

        $records = [];
        if ($user) {
            $records[] = [
                'source' => 'users',
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ];
        }
        if ($employee) {
            $records[] = [
                'source' => 'employees',
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'national_id' => $employee->national_id,
                'birth_date' => $employee->birth_date,
                'address' => $employee->address,
                'phone' => $employee->personal_phone,
                'blood_type' => $employee->blood_type,
                'iban' => $employee->iban,
                'emergency_contact_name' => $employee->emergency_contact_name,
                'emergency_contact_phone' => $employee->emergency_contact_phone,
                'hire_date' => $employee->hire_date,
                'status' => $employee->status,
                'department_id' => $employee->department_id,
                'gender' => $employee->gender,
                'custom_fields' => $employee->custom_fields,
            ];
        }

        return $records === [] ? [] : [[
            'category' => 'identity',
            'label' => 'Profil / özlük',
            'records' => $records,
            'files' => $user?->avatar ? [['path' => (string) $user->avatar, 'name' => 'avatar']] : [],
        ]];
    }

    public function destroy(string $subjectType, int $subjectId, int $companyId, string $strategy, bool $dryRun = false): array
    {
        $userId = $this->resolveUserId($subjectType, $subjectId, $companyId);
        $employeeId = $this->resolveEmployeeId($subjectType, $subjectId, $companyId);
        if (! $userId && ! $employeeId) {
            return AnonymizationHelper::emptyResult();
        }

        $fields = [
            'name', 'email', 'phone', 'national_id', 'iban', 'address',
            'personal_email', 'personal_phone', 'emergency_contact_name',
            'emergency_contact_phone', 'birth_date', 'avatar', 'bank_name',
            'sgk_number', 'gross_salary', 'net_salary',
        ];
        $files = [];
        $tables = [];
        $rows = 0;

        $user = $userId
            ? User::query()->where('home_company_id', $companyId)->where('id', $userId)->first()
            : null;
        $employee = $employeeId
            ? Employee::query()->where('company_id', $companyId)->where('id', $employeeId)->first()
            : null;

        if ($user) {
            $tables[] = 'users';
            $rows++;
            if ($user->avatar) {
                $files[] = (string) $user->avatar;
            }
            if (! $dryRun) {
                if ($strategy === 'delete') {
                    // Hard delete yalnız kullanıcı kaydı; FK kırılmaması için tercihen anonymize
                    $label = AnonymizationHelper::anonymLabel((int) $user->id);
                    $user->forceFill([
                        'name' => $label,
                        'email' => 'anon-'.$user->id.'@invalid.local',
                        'phone' => null,
                        'avatar' => null,
                    ])->save();
                } else {
                    $label = AnonymizationHelper::anonymLabel((int) $user->id);
                    if ($user->avatar) {
                        try {
                            Storage::disk('private')->delete((string) $user->avatar);
                        } catch (\Throwable) {
                            // dosya yoksa devam
                        }
                    }
                    $user->forceFill([
                        'name' => $label,
                        'email' => 'anon-'.$user->id.'@invalid.local',
                        'phone' => null,
                        'avatar' => null,
                    ])->save();
                }
            }
        }

        if ($employee) {
            $tables[] = 'employees';
            $rows++;
            if (! $dryRun) {
                $label = AnonymizationHelper::anonymLabel((int) $employee->id);
                $employee->forceFill([
                    'national_id' => null,
                    'iban' => null,
                    'bank_name' => null,
                    'address' => null,
                    'city' => null,
                    'district' => null,
                    'postal_code' => null,
                    'personal_email' => null,
                    'personal_phone' => null,
                    'emergency_contact_name' => null,
                    'emergency_contact_phone' => null,
                    'emergency_contact_relation' => null,
                    'blood_type' => null,
                    'sgk_number' => null,
                    'gross_salary' => null,
                    'net_salary' => null,
                    'birth_date' => AnonymizationHelper::yearOnly(
                        $employee->birth_date?->format('Y-m-d') ?? (is_string($employee->birth_date) ? $employee->birth_date : null)
                    ),
                    'title' => $employee->title, // istatistik
                    // department_id, gender, hire_date, status, position KALIR
                    'employee_code' => $employee->employee_code
                        ? 'ANON-'.substr(hash('sha256', (string) $employee->employee_code), 0, 8)
                        : $label,
                ])->save();
            }
        }

        return AnonymizationHelper::result($rows, $fields, $files, $tables);
    }
}
