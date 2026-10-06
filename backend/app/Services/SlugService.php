<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SlugService
{
    /** Generate a slug unique within the model's table (including soft-deleted rows). */
    public function unique(string $modelClass, string $source, ?int $ignoreId = null, string $column = 'slug', array $scope = []): string
    {
        $base = Str::slug($source) ?: Str::lower(Str::random(8));
        $slug = $base;
        $i = 2;
        while ($this->exists($modelClass, $column, $slug, $ignoreId, $scope)) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function exists(string $modelClass, string $column, string $slug, ?int $ignoreId, array $scope): bool
    {
        /** @var Model $model */
        $model = new $modelClass;
        $q = method_exists($model, 'bootSoftDeletes') ? $modelClass::withTrashed() : $modelClass::query();

        return $q->where($column, $slug)->where($scope)->when($ignoreId, fn ($w) => $w->whereKeyNot($ignoreId))->exists();
    }
}
