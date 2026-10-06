<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class ApiFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Trim strings and drop empty strings to null (the global middleware does this too). */
    protected function strip(string ...$keys): void
    {
        foreach ($keys as $k) {
            if (is_string($this->input($k))) {
                $this->merge([$k => strip_tags($this->input($k))]);
            }
        }
    }
}
