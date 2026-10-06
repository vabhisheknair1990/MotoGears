<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\VehicleManufacturerResource;
use App\Http\Resources\VehicleModelResource;
use App\Http\Resources\VehicleVariantResource;
use App\Models\VehicleManufacturer;
use App\Models\VehicleModel;
use App\Models\VehicleVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Powers the Make → Model → Year → Variant selector. */
class VehicleController extends Controller
{
    public function manufacturers(Request $request): JsonResponse
    {
        $request->validate(['vehicle_type' => ['nullable', Rule::in(['car', 'motorcycle'])]]);
        $list = VehicleManufacturer::active()->forType($request->vehicle_type)->withCount('models')->orderBy('sort_order')->orderBy('name')->get();

        return $this->ok(VehicleManufacturerResource::collection($list), 'Manufacturers retrieved successfully');
    }

    public function models(Request $request): JsonResponse
    {
        $request->validate([
            'manufacturer_id' => ['required', 'integer', 'exists:vehicle_manufacturers,id'],
            'vehicle_type' => ['nullable', Rule::in(['car', 'motorcycle'])],
        ]);
        $models = VehicleModel::active()->where('vehicle_manufacturer_id', $request->manufacturer_id)
            ->when($request->vehicle_type, fn ($q, $t) => $q->where('vehicle_type', $t))
            ->withCount('variants')->orderBy('name')->get();

        return $this->ok(VehicleModelResource::collection($models), 'Models retrieved successfully');
    }

    /** Distinct model years available for a model (derived from variant year ranges). */
    public function years(Request $request): JsonResponse
    {
        $request->validate(['model_id' => ['required', 'integer', 'exists:vehicle_models,id']]);
        $years = VehicleVariant::active()->where('vehicle_model_id', $request->model_id)->get()
            ->flatMap(fn (VehicleVariant $v) => $v->years())->unique()->sortDesc()->values();

        return $this->ok($years, 'Years retrieved successfully');
    }

    public function variants(Request $request): JsonResponse
    {
        $request->validate([
            'model_id' => ['required', 'integer', 'exists:vehicle_models,id'],
            'year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
        ]);
        $variants = VehicleVariant::active()->where('vehicle_model_id', $request->model_id)->forYear($request->integer('year') ?: null)
            ->orderBy('name')->get();

        return $this->ok(VehicleVariantResource::collection($variants), 'Variants retrieved successfully');
    }

    public function showVariant(VehicleVariant $variant): JsonResponse
    {
        return $this->ok(new VehicleVariantResource($variant->load('model.manufacturer')), 'Vehicle retrieved successfully');
    }
}
