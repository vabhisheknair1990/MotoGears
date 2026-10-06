<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'search' => ['nullable', 'string', 'max:100'],
            'product_id' => ['nullable', 'integer'],
        ]);
        $page = Review::with(['user:id,name,email', 'product' => fn ($q) => $q->withTrashed()->with('primaryImage'), 'images'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->rating, fn ($q, $r) => $q->where('rating', $r))
            ->when($request->product_id, fn ($q, $id) => $q->where('product_id', $id))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('title', 'like', "%{$s}%")->orWhere('comment', 'like', "%{$s}%")
                ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$s}%"))->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$s}%"))))
            ->latest('id')->paginate($this->perPage(20));

        return $this->paginated($page, ReviewResource::class, 'Reviews retrieved successfully', [
            'counts' => Review::selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    /** Approve / reject / feature. */
    public function update(Request $request, Review $review): JsonResponse
    {
        $this->authorize('moderate', $review);
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(['pending', 'approved', 'rejected'])],
            'is_featured' => ['sometimes', 'boolean'],
        ]);
        $review->update($data);
        $this->audit->changes('review.moderated', $review);

        return $this->ok(new ReviewResource($review->load(['user', 'product.primaryImage', 'images'])), 'Review updated');
    }

    public function bulk(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'max:100'], 'ids.*' => ['integer'], 'status' => ['required', Rule::in(['approved', 'rejected'])]]);
        Review::whereIn('id', $data['ids'])->get()->each(fn (Review $r) => $r->update(['status' => $data['status']]));
        $this->audit->log('review.bulk_'.$data['status'], null, null, ['ids' => $data['ids']]);

        return $this->ok(null, count($data['ids']).' review(s) '.$data['status']);
    }

    public function destroy(Review $review): JsonResponse
    {
        $this->authorize('delete', $review);
        $review->delete();
        $this->audit->log('review.deleted', $review);

        return $this->deleted('Review deleted');
    }
}
