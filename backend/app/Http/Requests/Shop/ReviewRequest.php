<?php

namespace App\Http\Requests\Shop;

use App\Http\Requests\ApiFormRequest;

class ReviewRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->strip('title', 'comment');
    }

    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:120'],
            'comment' => ['required', 'string', 'min:10', 'max:2000'],
            'images' => ['nullable', 'array', 'max:4'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }
}
