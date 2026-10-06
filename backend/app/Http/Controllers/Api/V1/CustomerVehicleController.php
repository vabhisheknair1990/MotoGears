<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\CustomerVehicleRequest;
use App\Http\Resources\CustomerVehicleResource;
use App\Models\CustomerVehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerVehicleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vehicles = $request->user()->vehicles()->with('variant.model.manufacturer')->orderByDesc('is_default')->latest()->get();

        return $this->ok(CustomerVehicleResource::collection($vehicles), 'Saved vehicles retrieved');
    }

    public function store(CustomerVehicleRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        if ($user->vehicles()->where('vehicle_variant_id', $data['vehicle_variant_id'])->where('year', $data['year'] ?? null)->exists()) {
            return response()->json(['success' => false, 'message' => 'This vehicle is already in your garage.'], 422);
        }
        $vehicle = DB::transaction(function () use ($user, $data) {
            $makeDefault = ($data['is_default'] ?? false) || ! $user->vehicles()->exists();
            if ($makeDefault) {
                $user->vehicles()->update(['is_default' => false]);
            }

            return $user->vehicles()->create(array_merge($data, ['is_default' => $makeDefault]));
        });

        return $this->created(new CustomerVehicleResource($vehicle->load('variant.model.manufacturer')), 'Vehicle added to your garage');
    }

    public function update(Request $request, CustomerVehicle $vehicle): JsonResponse
    {
        $this->authorize('update', $vehicle);
        $data = $request->validate([
            'nickname' => ['sometimes', 'nullable', 'string', 'max:60'],
            'registration_number' => ['sometimes', 'nullable', 'string', 'max:20'],
            'is_default' => ['sometimes', 'boolean'],
        ]);
        DB::transaction(function () use ($vehicle, $data) {
            if (! empty($data['is_default'])) {
                CustomerVehicle::where('user_id', $vehicle->user_id)->update(['is_default' => false]);
            }
            $vehicle->update($data);
        });

        return $this->ok(new CustomerVehicleResource($vehicle->fresh()->load('variant.model.manufacturer')), 'Vehicle updated');
    }

    public function destroy(CustomerVehicle $vehicle): JsonResponse
    {
        $this->authorize('delete', $vehicle);
        $wasDefault = $vehicle->is_default;
        $userId = $vehicle->user_id;
        $vehicle->delete();
        if ($wasDefault) {
            CustomerVehicle::where('user_id', $userId)->latest()->first()?->update(['is_default' => true]);
        }

        return $this->deleted('Vehicle removed from your garage');
    }
}
