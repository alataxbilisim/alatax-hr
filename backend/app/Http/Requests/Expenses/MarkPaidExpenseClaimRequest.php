<?php

namespace App\Http\Requests\Expenses;

use Illuminate\Foundation\Http\FormRequest;

class MarkPaidExpenseClaimRequest extends FormRequest
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
            'payment_method' => ['nullable', 'string', 'max:100'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
