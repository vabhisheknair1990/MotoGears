<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Faq;
use Illuminate\Database\Eloquent\Model;

class FaqController extends SimpleCrudController
{
    protected string $model = Faq::class;

    protected string $label = 'FAQ';

    protected array $searchable = ['question', 'answer', 'category'];

    protected array $orderBy = ['category' => 'asc', 'sort_order' => 'asc'];

    protected function rules(?Model $record): array
    {
        $req = $record ? 'sometimes' : 'required';

        return [
            'category' => [$req, 'string', 'max:60'],
            'question' => [$req, 'string', 'max:255'],
            'answer' => [$req, 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
