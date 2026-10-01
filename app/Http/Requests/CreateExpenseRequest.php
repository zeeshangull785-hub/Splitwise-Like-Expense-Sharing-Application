<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'paid_by' => ['nullable', 'string'],
            'split_type' => ['required', 'string', 'in:equal,exact,percentage'],
            'participants' => ['required', 'array', 'min:1'],
            'participants.*.user_id' => ['required', 'string'],
            'participants.*.amount' => ['nullable', 'numeric', 'gte:0'],
            'participants.*.percentage' => ['nullable', 'numeric', 'gte:0', 'lte:100'],
            'category' => ['nullable', 'string', 'in:Food,Travel,Rent,Shopping,Entertainment,Utilities,Other'],
            'receipt' => ['nullable', 'file', 'mimes:jpeg,png,jpg,gif,pdf', 'max:5120'],
        ];
    }
}
