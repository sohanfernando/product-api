<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'stock' => ['sometimes', 'required', 'integer', 'min:0', 'max:4294967295'],
            'user_id' => ['prohibited'],
        ];
    }
}
