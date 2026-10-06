<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CouponRequest;
use App\Http\Resources\CouponResource;
use App\Models\Coupon;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CouponController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['search' => ['nullable', 'string', 'max:50'], 'status' => ['nullable', 'in:active,inactive,expired']]);
        $page = Coupon::with(['products:id,name,sku', 'categories:id,name'])
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('code', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%")))
            ->when($request->status === 'active', fn ($q) => $q->currentlyValid())
            ->when($request->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($request->status === 'expired', fn ($q) => $q->where('expires_at', '<', now()))
            ->latest('id')->paginate($this->perPage(20));

        return $this->paginated($page, CouponResource::class, 'Coupons retrieved successfully');
    }

    public function show(Coupon $coupon): JsonResponse
    {
        return $this->ok(new CouponResource($coupon->load(['products:id,name,sku', 'categories:id,name'])), 'Coupon retrieved successfully');
    }

    public function store(CouponRequest $request): JsonResponse
    {
        $coupon = DB::transaction(function () use ($request) {
            $coupon = Coupon::create(Arr::except($request->validated(), ['product_ids', 'category_ids']));
            $coupon->products()->sync($request->input('product_ids', []));
            $coupon->categories()->sync($request->input('category_ids', []));

            return $coupon;
        });
        $this->audit->log('coupon.created', $coupon, null, $request->validated());

        return $this->created(new CouponResource($coupon->load(['products:id,name,sku', 'categories:id,name'])), 'Coupon created successfully');
    }

    public function update(CouponRequest $request, Coupon $coupon): JsonResponse
    {
        DB::transaction(function () use ($request, $coupon) {
            $coupon->update(Arr::except($request->validated(), ['product_ids', 'category_ids']));
            if ($request->has('product_ids')) {
                $coupon->products()->sync($request->input('product_ids', []));
            }
            if ($request->has('category_ids')) {
                $coupon->categories()->sync($request->input('category_ids', []));
            }
        });
        $this->audit->changes('coupon.updated', $coupon);

        return $this->ok(new CouponResource($coupon->fresh()->load(['products:id,name,sku', 'categories:id,name'])), 'Coupon updated successfully');
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        $coupon->update(['is_active' => false]);
        $coupon->delete();
        $this->audit->log('coupon.deleted', $coupon);

        return $this->deleted('Coupon deleted');
    }
}
