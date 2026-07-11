<?php

namespace App\Http\Resources;

use App\Services\EmployeeSensitiveFieldService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Personel API çıktısı — hassas alanlar izne göre (when); yetkisizde anahtar yok.
 *
 * @mixin \App\Models\Employee
 */
class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();
        $fields = app(EmployeeSensitiveFieldService::class);

        $data = [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'user_id' => $this->user_id,
            'department_id' => $this->department_id,
            'employee_code' => $this->employee_code,
            'title' => $this->title,
            'position' => $this->position,
            'manager_id' => $this->manager_id,
            'birth_date' => $this->birth_date,
            'gender' => $this->gender,
            'marital_status' => $this->marital_status,
            'blood_type' => $this->blood_type,
            'education_level' => $this->education_level,
            'personal_email' => $this->personal_email,
            'personal_phone' => $this->personal_phone,
            'address' => $this->address,
            'city' => $this->city,
            'district' => $this->district,
            'postal_code' => $this->postal_code,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'emergency_contact_relation' => $this->emergency_contact_relation,
            'hire_date' => $this->hire_date,
            'contract_start_date' => $this->contract_start_date,
            'contract_end_date' => $this->contract_end_date,
            'contract_type' => $this->contract_type,
            'work_type' => $this->work_type,
            'currency' => $this->currency,
            'status' => $this->status,
            'termination_date' => $this->termination_date,
            'termination_reason' => $this->termination_reason,
            'notes' => $this->notes,
            'custom_fields' => $this->custom_fields,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->when(array_key_exists('deleted_at', $this->resource->getAttributes()), $this->deleted_at),
            'user' => $this->whenLoaded('user'),
            'department' => $this->whenLoaded('department'),
            'manager' => $this->whenLoaded('manager', function () {
                return new self($this->manager);
            }),
            'subordinates' => $this->whenLoaded('subordinates', function () {
                return self::collection($this->subordinates);
            }),
            'documents' => $this->whenLoaded('documents'),
            'requests' => $this->whenLoaded('requests'),
        ];

        if ($fields->canViewSalary($user)) {
            $data['gross_salary'] = $this->gross_salary;
            $data['net_salary'] = $this->net_salary;
            $data['bank_name'] = $this->bank_name;
            $data['iban'] = $this->iban;
            $data['sgk_number'] = $this->sgk_number;
            $data['sgk_start_date'] = $this->sgk_start_date;
        }

        if ($fields->canViewTckn($user, $this->resource)) {
            $data['national_id'] = $this->national_id;
        }

        return $data;
    }
}
