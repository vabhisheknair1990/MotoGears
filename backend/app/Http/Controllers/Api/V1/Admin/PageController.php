<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Page;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class PageController extends SimpleCrudController
{
    protected string $model = Page::class;

    protected string $label = 'Page';

    protected array $searchable = ['title', 'slug'];

    protected array $orderBy = ['title' => 'asc'];

    protected ?string $slugFrom = 'title';

    protected function rules(?Model $record): array
    {
        $req = $record ? 'sometimes' : 'required';

        return [
            'title' => [$req, 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190', 'alpha_dash', Rule::unique('pages', 'slug')->ignore($record?->id)],
            'content' => [$req, 'string', 'max:100000'],
            'meta_title' => ['nullable', 'string', 'max:190'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
