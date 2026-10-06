<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCardResource;
use App\Models\Product;
use App\Models\Wishlist;
use App\Services\CartService;
use App\Services\ProductQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function __construct(private CartService $carts) {}

    public function index(Request $request): JsonResponse
    {
        return $this->ok($this->payload($request), 'Wishlist retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate(['product_id' => ['required', 'integer', 'exists:products,id']]);
        $product = Product::active()->findOrFail($request->product_id);
        $this->wishlist($request)->items()->firstOrCreate(['product_id' => $product->id]);

        return $this->ok($this->payload($request), 'Added to wishlist', [], 201);
    }

    public function destroy(Request $request, int $product): JsonResponse
    {
        $this->wishlist($request)->items()->where('product_id', $product)->delete();

        return $this->ok($this->payload($request), 'Removed from wishlist');
    }

    public function moveToCart(Request $request, int $product): JsonResponse
    {
        $item = $this->wishlist($request)->items()->where('product_id', $product)->firstOrFail();
        $this->carts->addItem($this->carts->forUser($request->user()), $item->product_id, 1);
        $item->delete();

        return $this->ok($this->payload($request), 'Moved to cart');
    }

    private function payload(Request $request): array
    {
        $items = $this->wishlist($request)->items()
            ->with(['product' => fn ($q) => $q->with(ProductQueryService::CARD_RELATIONS)])->get()
            ->filter(fn ($i) => $i->product && $i->product->is_active)->values();

        return [
            'items' => $items->map(fn ($i) => [
                'id' => $i->id,
                'product' => (new ProductCardResource($i->product))->resolve($request),
                'added_at' => $i->created_at?->toIso8601String(),
            ]),
            'count' => $items->count(),
            'product_ids' => $items->pluck('product_id'),
        ];
    }

    private function wishlist(Request $request): Wishlist
    {
        return Wishlist::firstOrCreate(['user_id' => $request->user()->id]);
    }
}
