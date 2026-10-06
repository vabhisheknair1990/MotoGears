<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\AddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $addresses = $request->user()->addresses()->orderByDesc('is_default')->latest()->get();

        return $this->ok(AddressResource::collection($addresses), 'Addresses retrieved');
    }

    public function store(AddressRequest $request): JsonResponse
    {
        $user = $request->user();
        if ($user->addresses()->count() >= 20) {
            return response()->json(['success' => false, 'message' => 'You can save up to 20 addresses.'], 422);
        }
        $address = DB::transaction(function () use ($request, $user) {
            $data = $request->validated();
            $makeDefault = ($data['is_default'] ?? false) || ! $user->addresses()->exists();
            if ($makeDefault) {
                $user->addresses()->update(['is_default' => false]);
            }

            return $user->addresses()->create(array_merge($data, ['is_default' => $makeDefault, 'country' => $data['country'] ?? 'IN']));
        });

        return $this->created(new AddressResource($address), 'Address saved');
    }

    public function update(AddressRequest $request, Address $address): JsonResponse
    {
        $this->authorize('update', $address);
        DB::transaction(function () use ($request, $address) {
            if ($request->boolean('is_default')) {
                $address->user->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
            }
            $address->update($request->validated());
        });

        return $this->ok(new AddressResource($address->fresh()), 'Address updated');
    }

    public function destroy(Address $address): JsonResponse
    {
        $this->authorize('delete', $address);
        $wasDefault = $address->is_default;
        $userId = $address->user_id;
        $address->delete();
        if ($wasDefault) {
            Address::where('user_id', $userId)->latest()->first()?->update(['is_default' => true]);
        }

        return $this->deleted('Address removed');
    }
}
