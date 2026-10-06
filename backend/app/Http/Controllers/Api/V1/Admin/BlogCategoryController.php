<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\BlogCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class BlogCategoryController extends SimpleCrudController
{
    protected string $model = BlogCategory::class;

    protected string $label = 'Blog category';

    protected array $searchable = ['name'];

    protected array $orderBy = ['name' => 'asc'];

    protected ?string $slugFrom = 'name';

    protected function rules(?Model $record): array
    {
        return [
            'name' => [$record ? 'sometimes' : 'required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:120', 'alpha_dash', Rule::unique('blog_categories', 'slug')->ignore($record?->id)],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
