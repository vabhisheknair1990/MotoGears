<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Model;

class TestimonialController extends SimpleCrudController
{
    protected string $model = Testimonial::class;

    protected string $label = 'Testimonial';

    protected array $searchable = ['name', 'content', 'vehicle'];

    protected array $orderBy = ['sort_order' => 'asc'];

    protected function rules(?Model $record): array
    {
        $req = $record ? 'sometimes' : 'required';

        return [
            'name' => [$req, 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:100'],
            'vehicle' => ['nullable', 'string', 'max:100'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'content' => [$req, 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
