<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\ContactRequest;
use App\Http\Resources\BannerResource;
use App\Http\Resources\BlogPostResource;
use App\Models\Banner;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\ContactMessage;
use App\Models\Faq;
use App\Models\NewsletterSubscriber;
use App\Models\Page;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CmsController extends Controller
{
    public function banners(Request $request): JsonResponse
    {
        $request->validate(['placement' => ['nullable', 'in:hero,promo,offer']]);
        $banners = Banner::live()->when($request->placement, fn ($q, $p) => $q->where('placement', $p))->get();

        return $this->ok(BannerResource::collection($banners), 'Banners retrieved successfully');
    }

    public function page(string $slug): JsonResponse
    {
        $page = Page::where('slug', $slug)->where('is_active', true)->firstOrFail();

        return $this->ok($page->only(['id', 'title', 'slug', 'content', 'meta_title', 'meta_description']), 'Page retrieved successfully');
    }

    public function faqs(): JsonResponse
    {
        $faqs = Faq::where('is_active', true)->orderBy('sort_order')->get(['id', 'category', 'question', 'answer']);

        return $this->ok($faqs->groupBy('category')->map(fn ($items, $cat) => ['category' => $cat, 'items' => $items->values()])->values(), 'FAQs retrieved successfully');
    }

    public function blog(Request $request): JsonResponse
    {
        $request->validate(['category' => ['nullable', 'string'], 'tag' => ['nullable', 'string'], 'search' => ['nullable', 'string', 'max:100']]);
        $page = BlogPost::published()->with(['category', 'author', 'tags'])
            ->when($request->category, fn ($q, $c) => $q->whereHas('category', fn ($w) => $w->where('slug', $c)))
            ->when($request->tag, fn ($q, $t) => $q->whereHas('tags', fn ($w) => $w->where('slug', $t)))
            ->when($request->search, fn ($q, $s) => $q->where('title', 'like', '%'.$s.'%'))
            ->latest('published_at')->paginate($this->perPage(9, 30));

        return $this->paginated($page, BlogPostResource::class, 'Blog posts retrieved successfully', [
            'categories' => BlogCategory::withCount(['posts' => fn ($q) => $q->published()])->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function blogPost(string $slug): JsonResponse
    {
        $post = BlogPost::published()->with(['category', 'author', 'tags'])->where('slug', $slug)->firstOrFail();
        $related = BlogPost::published()->whereKeyNot($post->id)->where('blog_category_id', $post->blog_category_id)->latest('published_at')->limit(3)->get();

        return $this->ok(array_merge((new BlogPostResource($post))->resolve(request()), [
            'related' => BlogPostResource::collection($related)->resolve(request()),
        ]), 'Blog post retrieved successfully');
    }

    public function contact(ContactRequest $request): JsonResponse
    {
        ContactMessage::create(array_merge($request->safe()->except('website'), ['ip_address' => $request->ip(), 'status' => 'new']));

        return $this->created(null, 'Thanks for reaching out! Our team will get back to you within one business day.');
    }

    public function newsletter(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190']]);
        NewsletterSubscriber::updateOrCreate(['email' => strtolower($data['email'])], ['status' => 'subscribed', 'source' => 'website']);

        return $this->created(null, 'You are subscribed! Watch your inbox for offers and maintenance tips.');
    }

    public function settings(SettingsService $settings): JsonResponse
    {
        return $this->ok(array_merge($settings->publicSettings(), [
            // Which payment options exist (used by "retry payment" screens outside checkout).
            'payment_methods' => \App\Services\Payments\PaymentManager::methodOptions(),
        ]), 'Settings retrieved successfully');
    }
}
