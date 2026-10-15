<?php

namespace App\Http\Requests\Leaves;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateLeaveBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'company_member'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'balances' => ['required', 'array', 'min:1'],
            // Şirket kontrolü satır bazında controller'da: yabancı tür tüm isteği değil yalnız o satırı reddeder.
            'balances.*.leave_type_id' => ['required', 'integer'],
            'balances.*.total_days' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Toplu bakiye güncellemesi için gerekçe zorunludur.',
        ];
    }
}
