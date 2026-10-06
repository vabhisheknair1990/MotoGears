<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VehicleManufacturerRequest;
use App\Http\Requests\Admin\VehicleModelRequest;
use App\Http\Requests\Admin\VehicleVariantRequest;
use App\Http\Resources\VehicleManufacturerResource;
use App\Http\Resources\VehicleModelResource;
use App\Http\Resources\VehicleVariantResource;
use App\Models\ProductCompatibility;
use App\Models\VehicleManufacturer;
use App\Models\VehicleModel;
use App\Models\VehicleVariant;
use App\Services\AuditLogger;
use App\Services\ImageUploadService;
use App\Services\SlugService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/** Manufacturers → Models → Variants management. */
class VehicleController extends Controller
{
    public function __construct(private SlugService $slugs, private ImageUploadService $uploads, private AuditLogger $audit) {}

    // ── Manufacturers ───────────────────────────────────────────────
    public function manufacturers(Request $request): JsonResponse
    {
        $request->validate(['search' => ['nullable', 'string', 'max:100'], 'vehicle_type' => ['nullable', 'in:car,motorcycle,both']]);
        $list = VehicleManufacturer::withCount('models')
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', '%'.$s.'%'))
            ->when($request->vehicle_type, fn ($q, $t) => $q->forType($t))
            ->orderBy('sort_order')->orderBy('name')->get();

        return $this->ok(VehicleManufacturerResource::collection($list), 'Manufacturers retrieved');
    }

    public function storeManufacturer(VehicleManufacturerRequest $request): JsonResponse
    {
        $data = Arr::except($request->validated(), ['logo']);
        $data['slug'] = $this->slugs->unique(VehicleManufacturer::class, $data['name']);
        if ($request->hasFile('logo')) {
            $data['logo_path'] = $this->uploads->store($request->file('logo'), 'manufacturers');
        }
        $m = VehicleManufacturer::create($data);
        $this->audit->log('vehicle_manufacturer.created', $m, null, $data);

        return $this->created(new VehicleManufacturerResource($m->loadCount('models')), 'Manufacturer created');
    }

    public function updateManufacturer(VehicleManufacturerRequest $request, VehicleManufacturer $manufacturer): JsonResponse
    {
        $data = Arr::except($request->validated(), ['logo']);
        if (isset($data['name']) && $data['name'] !== $manufacturer->name) {
            $data['slug'] = $this->slugs->unique(VehicleManufacturer::class, $data['name'], $manufacturer->id);
        }
        if ($request->hasFile('logo')) {
            $this->uploads->delete($manufacturer->logo_path);
            $data['logo_path'] = $this->uploads->store($request->file('logo'), 'manufacturers');
        }
        $manufacturer->update($data);
        $this->audit->changes('vehicle_manufacturer.updated', $manufacturer);

        return $this->ok(new VehicleManufacturerResource($manufacturer->loadCount('models')), 'Manufacturer updated');
    }

    public function destroyManufacturer(VehicleManufacturer $manufacturer): JsonResponse
    {
        if (ProductCompatibility::where('vehicle_manufacturer_id', $manufacturer->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Products are mapped to this manufacturer. Remove those compatibility records or deactivate it instead.'], 422);
        }
        $manufacturer->delete();
        $this->audit->log('vehicle_manufacturer.deleted', $manufacturer);

        return $this->deleted('Manufacturer deleted');
    }

    // ── Models ──────────────────────────────────────────────────────
    public function models(Request $request): JsonResponse
    {
        $request->validate(['manufacturer_id' => ['nullable', 'integer'], 'search' => ['nullable', 'string', 'max:100']]);
        $list = VehicleModel::with('manufacturer')->withCount('variants')
            ->when($request->manufacturer_id, fn ($q, $id) => $q->where('vehicle_manufacturer_id', $id))
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', '%'.$s.'%'))
            ->orderBy('name')->get();

        return $this->ok(VehicleModelResource::collection($list), 'Models retrieved');
    }

    public function storeModel(VehicleModelRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = $this->slugs->unique(VehicleModel::class, $data['name'], null, 'slug', ['vehicle_manufacturer_id' => $data['vehicle_manufacturer_id']]);
        $model = VehicleModel::create($data);
        $this->audit->log('vehicle_model.created', $model, null, $data);

        return $this->created(new VehicleModelResource($model->load('manufacturer')->loadCount('variants')), 'Model created');
    }

    public function updateModel(VehicleModelRequest $request, VehicleModel $model): JsonResponse
    {
        $data = $request->validated();
        if (isset($data['name']) && $data['name'] !== $model->name) {
            $data['slug'] = $this->slugs->unique(VehicleModel::class, $data['name'], $model->id, 'slug', ['vehicle_manufacturer_id' => $data['vehicle_manufacturer_id'] ?? $model->vehicle_manufacturer_id]);
        }
        $model->update($data);
        $this->audit->changes('vehicle_model.updated', $model);

        return $this->ok(new VehicleModelResource($model->load('manufacturer')->loadCount('variants')), 'Model updated');
    }

    public function destroyModel(VehicleModel $model): JsonResponse
    {
        if (ProductCompatibility::where('vehicle_model_id', $model->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Products are mapped to this model. Remove those compatibility records or deactivate it instead.'], 422);
        }
        $model->delete();
        $this->audit->log('vehicle_model.deleted', $model);

        return $this->deleted('Model deleted');
    }

    // ── Variants ────────────────────────────────────────────────────
    public function variants(Request $request): JsonResponse
    {
        $request->validate(['model_id' => ['nullable', 'integer'], 'manufacturer_id' => ['nullable', 'integer']]);
        $list = VehicleVariant::with('model.manufacturer')
            ->when($request->model_id, fn ($q, $id) => $q->where('vehicle_model_id', $id))
            ->when($request->manufacturer_id, fn ($q, $id) => $q->whereHas('model', fn ($m) => $m->where('vehicle_manufacturer_id', $id)))
            ->orderBy('vehicle_model_id')->orderBy('name')->get();

        return $this->ok(VehicleVariantResource::collection($list), 'Variants retrieved');
    }

    public function storeVariant(VehicleVariantRequest $request): JsonResponse
    {
        $variant = VehicleVariant::create($request->validated());
        $this->audit->log('vehicle_variant.created', $variant, null, $request->validated());

        return $this->created(new VehicleVariantResource($variant->load('model.manufacturer')), 'Variant created');
    }

    public function updateVariant(VehicleVariantRequest $request, VehicleVariant $variant): JsonResponse
    {
        $variant->update($request->validated());
        $this->audit->changes('vehicle_variant.updated', $variant);

        return $this->ok(new VehicleVariantResource($variant->load('model.manufacturer')), 'Variant updated');
    }

    public function destroyVariant(VehicleVariant $variant): JsonResponse
    {
        if (ProductCompatibility::where('vehicle_variant_id', $variant->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Products are mapped to this variant. Remove those compatibility records or deactivate it instead.'], 422);
        }
        $variant->delete();
        $this->audit->log('vehicle_variant.deleted', $variant);

        return $this->deleted('Variant deleted');
    }
}
