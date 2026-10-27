<?php

namespace App\Http\Requests;

class UpdateProductRequest extends StoreProductRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['name'] = ['sometimes', ...$rules['name']];
        $rules['price'] = ['sometimes', ...$rules['price']];

        return $rules;
    }
}
