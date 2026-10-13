<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Banner;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Support\Catalog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Turns a store that was started with demo data into a live store:
 *  - demo people: every *@example.com account (demo staff + customers) with their orders,
 *    addresses, carts, reviews…, guest orders placed with an @example.com email, and the
 *    demo contact messages / newsletter sign-ups;
 *  - demo coupons (except a code a home-page banner still advertises);
 *  - the whole car catalogue: car products, car categories, car makes/models/variants,
 *    brands that only sold car parts, and car testimonials, blog posts and search terms.
 *
 * Kept: real orders/customers, staff, bike & universal products, categories, brands, bike
 * makes, settings, pages, FAQs and the other sample content. Stock reservations and product
 * ratings are recalculated afterwards.
 */
class LiveDataCleaner
{
    public const DEMO_COUPONS = ['WELCOME10', 'SAVE500', 'FREESHIP', 'BRAKES15', 'RIDE20', 'MONSOON300'];

    /** Orders in these states still hold reserved stock. */
    private const OPEN = [OrderStatus::Pending, OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::Packed];

    /** What would be removed, as id lists per kind (nothing is changed). */
    public function plan(): array
    {
        $demoUsers = DB::table('users')->where('email', 'like', '%@example.com')->pluck('id')->all();
        $demoOrders = DB::table('orders')->whereIn('user_id', $demoUsers)->orWhere('customer_email', 'like', '%@example.com')->pluck('id')->all();

        $carProducts = DB::table('products')->where('vehicle_type', 'car')->pluck('id')->all();
        // Brands whose every product is a car part (brands without products are kept).
        $carBrands = DB::table('brands')
            ->whereExists(fn ($q) => $q->from('products')->whereColumn('products.brand_id', 'brands.id'))
            ->whereNotExists(fn ($q) => $q->from('products')->whereColumn('products.brand_id', 'brands.id')->where('vehicle_type', '!=', 'car'))
            ->pluck('id')->all();
        $carCategories = DB::table('categories')->where('vehicle_type', 'car')->pluck('id')->all();
        $carModels = DB::table('vehicle_models')->where('vehicle_type', 'car')->pluck('id')->all();
        $carMakers = DB::table('vehicle_manufacturers')->where('vehicle_type', 'car')->pluck('id')->all();

        $testimonials = DB::table('testimonials')->get(['id', 'vehicle', 'content'])
            ->filter(fn ($t) => Catalog::mentionsCar($t->vehicle.' '.$t->content))->pluck('id')->all();
        $posts = DB::table('blog_posts')->get(['id', 'title'])->filter(fn ($p) => Catalog::mentionsCar($p->title))->pluck('id')->all();
        $terms = DB::table('search_terms')->get(['id', 'term'])->filter(fn ($t) => Catalog::mentionsCar($t->term))->pluck('id')->all();

        // A code a live banner still advertises stays usable.
        $advertised = Banner::query()->where('is_active', true)->get()
            ->flatMap(fn ($b) => array_filter(self::DEMO_COUPONS, fn ($code) => str_contains(strtoupper($b->title.' '.$b->subtitle.' '.$b->eyebrow.' '.$b->cta_label.' '.$b->cta_url), $code)))
            ->unique()->all();
        $coupons = DB::table('coupons')->whereIn('code', array_diff(self::DEMO_COUPONS, $advertised))->pluck('id')->all();

        return [
            'demo_users' => $demoUsers,
            'demo_orders' => $demoOrders,
            'car_products' => $carProducts,
            'car_brands' => $carBrands,
            'car_categories' => $carCategories,
            'car_models' => $carModels,
            'car_makers' => $carMakers,
            'car_testimonials' => $testimonials,
            'car_blog_posts' => $posts,
            'car_search_terms' => $terms,
            'demo_coupons' => $coupons,
            'kept_coupons' => array_values($advertised),
            'demo_messages' => DB::table('contact_messages')->where('email', 'like', '%@example.com')->pluck('id')->all(),
            'demo_subscribers' => DB::table('newsletter_subscribers')->where('email', 'like', '%@example.com')->pluck('id')->all(),
        ];
    }

    /** A real (non-demo) active super admin who will still be able to sign in afterwards. */
    public function hasRealSuperAdmin(): bool
    {
        return User::query()->where('email', 'not like', '%@example.com')->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('name', Role::SUPER_ADMIN))->exists();
    }

    /** Remove everything in the plan. Returns how many rows of each kind went. */
    public function run(array $plan): array
    {
        $files = array_merge(
            DB::table('product_images')->whereIn('product_id', $plan['car_products'])->pluck('path')->all(),
            DB::table('review_images')->whereIn('review_id', DB::table('reviews')
                ->whereIn('product_id', $plan['car_products'])->orWhereIn('user_id', $plan['demo_users'])->select('id'))->pluck('path')->all(),
        );

        $done = DB::transaction(function () use ($plan) {
            $n = [];
            $n['orders'] = DB::table('orders')->whereIn('id', $plan['demo_orders'])->delete();

            $users = $plan['demo_users'];
            DB::table('personal_access_tokens')->where('tokenable_type', User::class)->whereIn('tokenable_id', $users)->delete();
            DB::table('notifications')->where('notifiable_type', User::class)->whereIn('notifiable_id', $users)->delete();
            DB::table('sessions')->whereIn('user_id', $users)->delete();
            $n['users'] = DB::table('users')->whereIn('id', $users)->delete();

            $n['products'] = DB::table('products')->whereIn('id', $plan['car_products'])->delete();
            $n['brands'] = DB::table('brands')->whereIn('id', $plan['car_brands'])
                ->whereNotExists(fn ($q) => $q->from('products')->whereColumn('products.brand_id', 'brands.id'))->delete();

            // Children before parents; a category that still holds products is left alone.
            $n['categories'] = 0;
            $left = $plan['car_categories'];
            for ($pass = 0; $pass < 5 && $left; $pass++) {
                $deletable = DB::table('categories')->whereIn('id', $left)
                    ->whereNotExists(fn ($q) => $q->from('products')->whereColumn('products.category_id', 'categories.id'))
                    ->whereNotExists(fn ($q) => $q->from('categories as c')->whereColumn('c.parent_id', 'categories.id'))
                    ->pluck('id')->all();
                $n['categories'] += DB::table('categories')->whereIn('id', $deletable)->delete();
                $left = array_diff($left, $deletable);
            }
            $n['categories_kept'] = count($left);

            $n['vehicle_models'] = DB::table('vehicle_models')->whereIn('id', $plan['car_models'])->delete();
            $n['vehicle_makers'] = DB::table('vehicle_manufacturers')->whereIn('id', $plan['car_makers'])
                ->whereNotExists(fn ($q) => $q->from('vehicle_models')->whereColumn('vehicle_models.vehicle_manufacturer_id', 'vehicle_manufacturers.id'))->delete();
            // Makers that built both (e.g. Honda) now only have bikes here.
            DB::table('vehicle_manufacturers')->where('vehicle_type', 'both')
                ->whereNotExists(fn ($q) => $q->from('vehicle_models')->whereColumn('vehicle_models.vehicle_manufacturer_id', 'vehicle_manufacturers.id')->where('vehicle_type', 'car'))
                ->update(['vehicle_type' => 'motorcycle', 'updated_at' => now()]);

            $n['testimonials'] = DB::table('testimonials')->whereIn('id', $plan['car_testimonials'])->delete();
            $n['blog_posts'] = DB::table('blog_posts')->whereIn('id', $plan['car_blog_posts'])->delete();
            $n['search_terms'] = DB::table('search_terms')->whereIn('id', $plan['car_search_terms'])->delete();
            $n['coupons'] = DB::table('coupons')->whereIn('id', $plan['demo_coupons'])->delete();
            $n['contact_messages'] = DB::table('contact_messages')->whereIn('id', $plan['demo_messages'])->delete();
            $n['newsletter_subscribers'] = DB::table('newsletter_subscribers')->whereIn('id', $plan['demo_subscribers'])->delete();

            $this->recountReservedStock();

            return $n;
        });

        Storage::disk('public')->delete(array_values(array_filter($files)));
        Product::query()->each(fn (Product $p) => $p->refreshRating());
        StorefrontCache::flush();
        foreach (['settings.all', 'catalog.car_names'] as $key) {
            Cache::forget($key);
        }

        return $done;
    }

    /** reserved = units in orders that are still open (the deleted demo orders held some). */
    private function recountReservedStock(): void
    {
        $open = array_map(fn (OrderStatus $s) => $s->value, self::OPEN);
        $held = DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', $open)->whereNotNull('order_items.product_id')
            ->groupBy('order_items.product_id')->selectRaw('order_items.product_id, SUM(order_items.quantity) as qty')
            ->pluck('qty', 'product_id');
        DB::table('inventories')->orderBy('id')->each(function ($inv) use ($held) {
            $reserved = (int) ($held[$inv->product_id] ?? 0);
            if ((int) $inv->reserved !== $reserved) {
                DB::table('inventories')->where('id', $inv->id)->update(['reserved' => $reserved, 'updated_at' => now()]);
            }
        });
    }
}
