<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ReviewResource;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    private function withStats(Builder $q): Builder
    {
        $revenue = array_map(fn ($s) => $s->value, OrderStatus::revenueStatuses());

        return $q->withCount('orders')
            ->withSum(['orders as total_spent' => fn ($o) => $o->whereIn('status', $revenue)], 'grand_total')
            ->withMax('orders as last_order_at', 'placed_at');
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'sort' => ['nullable', Rule::in(['newest', 'name', 'spent', 'orders'])],
        ]);
        $q = $this->withStats(User::customers())
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")))
            ->when($request->status, fn ($q, $s) => $q->where('is_active', $s === 'active'));
        match ($request->sort) {
            'name' => $q->orderBy('name'),
            'spent' => $q->orderByDesc('total_spent'),
            'orders' => $q->orderByDesc('orders_count'),
            default => $q->latest('id'),
        };

        return $this->paginated($q->paginate($this->perPage(20)), CustomerResource::class, 'Customers retrieved successfully');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $customer = $this->withStats(User::customers())->with(['addresses', 'vehicles.variant.model.manufacturer'])->findOrFail($id);

        return $this->ok(array_merge((new CustomerResource($customer))->resolve($request), [
            'orders' => OrderResource::collection($customer->orders()->with('items')->withCount('items')->latest()->limit(20)->get())->resolve($request),
            'reviews' => ReviewResource::collection($customer->reviews()->with(['product.primaryImage', 'user', 'images'])->latest()->limit(20)->get())->resolve($request),
            'average_order_value' => $customer->orders_count ? round((float) $customer->total_spent / max(1, $customer->orders()->revenue()->count()), 2) : 0,
        ]), 'Customer retrieved successfully');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $customer = User::customers()->findOrFail($id);
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $customer->forceFill($data)->save();
        if (! $customer->is_active) {
            $customer->tokens()->delete();
        }
        $this->audit->changes($customer->is_active ? 'customer.enabled' : 'customer.disabled', $customer);

        return $this->ok(['id' => $customer->id, 'is_active' => $customer->is_active], $customer->is_active ? 'Customer enabled' : 'Customer disabled');
    }
}
