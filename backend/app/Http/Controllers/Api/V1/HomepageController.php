<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BannerResource;
use App\Http\Resources\BlogPostResource;
use App\Http\Resources\BrandResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CouponResource;
use App\Http\Resources\ProductCardResource;
use App\Models\Banner;
use App\Models\BlogPost;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\Testimonial;
use App\Models\VehicleVariant;
use App\Services\ProductQueryService;
use App\Services\StorefrontCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * One aggregated call for the whole homepage. The shared part is cached and flushed whenever
 * banners, products, categories, brands, testimonials or posts change. Vehicle-specific
 * recommendations are computed per request.
 */
class HomepageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = StorefrontCache::remember('storefront.homepage', fn () => $this->build($request));

        if ($variantId = $request->integer('vehicle_variant')) {
            $variant = VehicleVariant::with('model.manufacturer')->find($variantId);
            if ($variant) {
                $data['for_your_vehicle'] = [
                    'vehicle' => ['id' => $variant->id, 'name' => $variant->fullName()],
                    'products' => ProductCardResource::collection(
                        Product::active()->with(ProductQueryService::CARD_RELATIONS)->compatibleWith($variant)
                            ->where('is_universal', false)->orderByDesc('sold_count')->limit(8)->get()
                    )->resolve($request),
                ];
            }
        }

        return $this->ok($data, 'Homepage retrieved successfully');
    }

    private function build(Request $request): array
    {
        $cards = fn ($q) => ProductCardResource::collection($q->with(ProductQueryService::CARD_RELATIONS)->limit(8)->get())->resolve($request);

        return [
            'hero_banners' => BannerResource::collection(Banner::live()->where('placement', 'hero')->get())->resolve($request),
            'promo_banners' => BannerResource::collection(Banner::live()->whereIn('placement', ['promo', 'offer'])->get())->resolve($request),
            'featured_categories' => CategoryResource::collection(
                Category::active()->where('is_featured', true)->withCount(['products' => fn ($q) => $q->where('is_active', true)])->orderBy('sort_order')->limit(12)->get()
            )->resolve($request),
            'featured_products' => $cards(Product::active()->where('is_featured', true)->orderByDesc('sold_count')),
            'best_sellers' => $cards(Product::active()->orderByDesc('sold_count')),
            'new_arrivals' => $cards(Product::active()->latest('created_at')->latest('id')),
            'deals' => $cards(Product::active()->whereRaw('mrp > 0 AND (mrp - price) * 100 >= 20 * mrp')->orderByRaw('(mrp - price) * 1.0 / mrp DESC')),
            'brands' => BrandResource::collection(Brand::active()->orderByDesc('is_featured')->orderBy('sort_order')->limit(16)->get())->resolve($request),
            'offers' => CouponResource::collection(Coupon::currentlyValid()->where(fn ($w) => $w->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'))->orderBy('min_order_amount')->limit(4)->get())->resolve($request),
            'testimonials' => Testimonial::where('is_active', true)->orderBy('sort_order')->limit(6)->get(['id', 'name', 'location', 'vehicle', 'rating', 'content']),
            'blog_posts' => BlogPostResource::collection(BlogPost::published()->with('category')->latest('published_at')->limit(3)->get())->resolve($request),
            'stats' => [
                'products' => Product::active()->count(),
                'brands' => Brand::active()->count(),
                'vehicles' => VehicleVariant::active()->count(),
            ],
        ];
    }
}
