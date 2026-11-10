<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],

            'min_price' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
                'decimal:0,2',
            ],

            'max_price' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
                'decimal:0,2',
                Rule::when($this->filled('min_price'), ['gte:min_price']),
            ],

            'in_stock' => ['sometimes', 'boolean'],

            'sort' => ['sometimes', Rule::in(['name', 'price', 'created_at'])],

            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }
}
