<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\ReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    public function __construct(private ImageUploadService $uploads) {}

    public function index(Request $request, Product $product): JsonResponse
    {
        $request->validate(['sort' => ['nullable', 'in:recent,highest,lowest,helpful'], 'rating' => ['nullable', 'integer', 'between:1,5']]);
        $base = Review::approved()->where('product_id', $product->id);

        $page = (clone $base)->with(['user:id,name', 'images'])
            ->when($request->rating, fn ($q, $r) => $q->where('rating', $r))
            ->when($request->sort, fn ($q, $s) => match ($s) {
                'highest' => $q->orderByDesc('rating'),
                'lowest' => $q->orderBy('rating'),
                'helpful' => $q->orderByDesc('helpful_count'),
                default => $q->latest(),
            }, fn ($q) => $q->orderByDesc('is_featured')->latest())
            ->paginate($this->perPage(10, 50));

        $distribution = (clone $base)->selectRaw('rating, COUNT(*) as c')->groupBy('rating')->pluck('c', 'rating');

        return $this->paginated($page, ReviewResource::class, 'Reviews retrieved successfully', [
            'summary' => [
                'average' => (float) $product->rating_avg,
                'count' => (int) $product->rating_count,
                'distribution' => collect([5, 4, 3, 2, 1])->mapWithKeys(fn ($r) => [$r => (int) ($distribution[$r] ?? 0)]),
            ],
        ]);
    }

    public function store(ReviewRequest $request, Product $product): JsonResponse
    {
        abort_unless($product->is_active, 404);
        $user = $request->user();
        if (Review::withTrashed()->where('product_id', $product->id)->where('user_id', $user->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'You have already reviewed this product.'], 422);
        }

        // Verified purchase = the customer has a non-cancelled order containing this product.
        $orderItem = OrderItem::where('product_id', $product->id)
            ->whereHas('order', fn ($q) => $q->where('user_id', $user->id)
                ->whereNotIn('status', [OrderStatus::Cancelled->value, OrderStatus::Pending->value, OrderStatus::Refunded->value]))
            ->latest('id')->first();

        if (! $orderItem) {
            return response()->json([
                'success' => false,
                'message' => 'Only customers who have purchased this product can review it.',
            ], 403);
        }

        $review = DB::transaction(function () use ($request, $product, $user, $orderItem) {
            $review = Review::create([
                'product_id' => $product->id,
                'user_id' => $user->id,
                'order_id' => $orderItem->order_id,
                'rating' => $request->rating,
                'title' => $request->title,
                'comment' => $request->comment,
                'status' => ReviewStatus::Pending,
                'is_verified_purchase' => true,
            ]);
            foreach ((array) $request->file('images', []) as $file) {
                $review->images()->create(['path' => $this->uploads->store($file, 'reviews')]);
            }

            return $review;
        });

        return $this->created(new ReviewResource($review->load(['user', 'images'])), 'Thanks! Your review has been submitted and will appear once approved.');
    }

    public function mine(Request $request): JsonResponse
    {
        $page = $request->user()->reviews()->with(['product.primaryImage', 'images', 'user'])->latest()->paginate($this->perPage(10));

        return $this->paginated($page, ReviewResource::class, 'Your reviews retrieved');
    }

    public function destroy(Request $request, Review $review): JsonResponse
    {
        $this->authorize('delete', $review);
        $review->delete();

        return $this->deleted('Review deleted');
    }
}
