<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SimpleResource;
use App\Services\AuditLogger;
use App\Services\SlugService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shared CRUD for small CMS tables. Subclasses declare the model, validation rules,
 * searchable columns and default ordering.
 */
abstract class SimpleCrudController extends Controller
{
    /** @var class-string<Model> */
    protected string $model;

    protected string $label = 'Record';

    protected array $searchable = [];

    protected array $orderBy = ['id' => 'desc'];

    /** When set, a slug is generated from this attribute if none is supplied. */
    protected ?string $slugFrom = null;

    abstract protected function rules(?Model $record): array;

    public function index(Request $request): JsonResponse
    {
        $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $q = $this->model::query()->when($request->search && $this->searchable, function ($q) use ($request) {
            $q->where(function ($w) use ($request) {
                foreach ($this->searchable as $col) {
                    $w->orWhere($col, 'like', '%'.$request->search.'%');
                }
            });
        });
        $this->filter($q, $request);
        foreach ($this->orderBy as $col => $dir) {
            $q->orderBy($col, $dir);
        }

        return $this->paginated($q->paginate($this->perPage(25, 200)), SimpleResource::class, $this->label.'s retrieved successfully');
    }

    public function show(int $id): JsonResponse
    {
        return $this->ok(new SimpleResource($this->model::findOrFail($id)), $this->label.' retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules(null));
        if ($this->slugFrom && empty($data['slug'])) {
            $data['slug'] = app(SlugService::class)->unique($this->model, $data[$this->slugFrom]);
        }
        $record = $this->model::create($data);
        app(AuditLogger::class)->log(strtolower(class_basename($this->model)).'.created', $record, null, $data);

        return $this->created(new SimpleResource($record->fresh()), $this->label.' created successfully');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $record = $this->model::findOrFail($id);
        $data = $request->validate($this->rules($record));
        if ($this->slugFrom && array_key_exists('slug', $data) && empty($data['slug'])) {
            unset($data['slug']);
        }
        $record->update($data);
        app(AuditLogger::class)->changes(strtolower(class_basename($this->model)).'.updated', $record);

        return $this->ok(new SimpleResource($record->fresh()), $this->label.' updated successfully');
    }

    public function destroy(int $id): JsonResponse
    {
        $record = $this->model::findOrFail($id);
        $record->delete();
        app(AuditLogger::class)->log(strtolower(class_basename($this->model)).'.deleted', $record);

        return $this->deleted($this->label.' deleted');
    }

    protected function filter($query, Request $request): void {}
}
