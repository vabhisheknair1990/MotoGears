<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Query\Builder as QB;
use Illuminate\Support\Facades\DB;

/**
 * Aggregations for the admin dashboard and reports. All figures are computed in SQL from the
 * orders tables — nothing is hard-coded. Revenue counts only orders in "revenue" statuses
 * (confirmed through delivered).
 */
class ReportService
{
    public const PERIODS = ['today', 'yesterday', 'last_7_days', 'last_30_days', 'this_month', 'last_month', 'this_year', 'custom'];

    /** @return array{0: Carbon, 1: Carbon, 2: string} */
    public function range(?string $period, ?string $from = null, ?string $to = null): array
    {
        $now = now();

        [$start, $end] = match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'last_7_days' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfDay()],
            'custom' => [Carbon::parse($from ?? $now->copy()->subDays(29))->startOfDay(), Carbon::parse($to ?? $now)->endOfDay()],
            default => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
        };

        return [$start, $end, $period && in_array($period, self::PERIODS, true) ? $period : 'last_30_days'];
    }

    public function dashboard(): array
    {
        $today = [now()->startOfDay(), now()->endOfDay()];
        $month = [now()->startOfMonth(), now()->endOfDay()];
        $lastMonth = [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()];

        $revenue = fn (array $r) => (float) Order::revenue()->whereBetween('placed_at', $r)->sum('grand_total');
        $thisMonth = $revenue($month);
        $prevMonth = $revenue($lastMonth);

        $inventory = Inventory::query();

        return [
            'sales' => [
                'total' => round((float) Order::revenue()->sum('grand_total'), 2),
                'today' => round($revenue($today), 2),
                'this_month' => round($thisMonth, 2),
                'last_month' => round($prevMonth, 2),
                'growth_percent' => $prevMonth > 0 ? round(($thisMonth - $prevMonth) / $prevMonth * 100, 1) : null,
                'average_order_value' => round((float) Order::revenue()->avg('grand_total'), 2),
            ],
            'orders' => [
                'total' => Order::count(),
                'today' => Order::whereBetween('placed_at', $today)->count(),
                'pending' => Order::whereIn('status', [OrderStatus::Pending->value, OrderStatus::Confirmed->value])->count(),
                'processing' => Order::whereIn('status', [OrderStatus::Processing->value, OrderStatus::Packed->value])->count(),
                'shipped' => Order::whereIn('status', [OrderStatus::Shipped->value, OrderStatus::OutForDelivery->value])->count(),
                'delivered' => Order::where('status', OrderStatus::Delivered->value)->count(),
                'cancelled' => Order::where('status', OrderStatus::Cancelled->value)->count(),
                'by_status' => Order::selectRaw('status, COUNT(*) as count')->groupBy('status')->pluck('count', 'status')
                    ->map(fn ($c, $s) => ['status' => $s, 'label' => OrderStatus::from($s)->label(), 'count' => (int) $c])->values(),
            ],
            'customers' => [
                'total' => User::customers()->count(),
                'new_this_month' => User::customers()->whereBetween('created_at', $month)->count(),
            ],
            'products' => [
                'total' => DB::table('products')->whereNull('deleted_at')->count(),
                'active' => DB::table('products')->whereNull('deleted_at')->where('is_active', true)->count(),
            ],
            'inventory' => [
                'low_stock' => (clone $inventory)->lowStock()->count(),
                'out_of_stock' => (clone $inventory)->outOfStock()->where('allow_backorder', false)->count(),
                'units_on_hand' => (int) (clone $inventory)->sum('quantity'),
                'units_reserved' => (int) (clone $inventory)->sum('reserved'),
            ],
            'top_products' => $this->topProducts(now()->subDays(29)->startOfDay(), now()->endOfDay(), 5),
            'top_categories' => $this->topGrouped('categories', now()->subDays(29)->startOfDay(), now()->endOfDay(), 5),
            'top_brands' => $this->topGrouped('brands', now()->subDays(29)->startOfDay(), now()->endOfDay(), 5),
            'recent_orders' => Order::with('user:id,name,email')->withCount('items')->latest('id')->limit(8)->get()->map(fn (Order $o) => [
                'id' => $o->id, 'order_number' => $o->order_number, 'customer' => $o->user?->name,
                'status' => $o->status->value, 'status_label' => $o->status->label(),
                'payment_status' => $o->payment_status->value, 'grand_total' => (float) $o->grand_total,
                'items_count' => $o->items_count, 'placed_at' => $o->placed_at?->toIso8601String(),
            ]),
            'low_stock' => Inventory::with('product:id,name,slug,sku')->where(fn ($q) => $q->lowStock()->orWhere(fn ($w) => $w->outOfStock()))
                ->orderByRaw('(quantity - reserved) asc')->limit(8)->get()->map(fn (Inventory $i) => [
                    'id' => $i->id, 'sku' => $i->sku, 'product' => $i->product?->name, 'product_id' => $i->product_id,
                    'available' => $i->available(), 'threshold' => $i->low_stock_threshold, 'status' => $i->status(),
                ]),
            'sales_chart' => $this->dailySeries(now()->subDays(29)->startOfDay(), now()->endOfDay()),
            'monthly_chart' => $this->monthlySeries(12),
            'payment_methods' => $this->byPaymentMethod(now()->subDays(29)->startOfDay(), now()->endOfDay()),
        ];
    }

    public function sales(Carbon $from, Carbon $to): array
    {
        $base = Order::revenue()->whereBetween('placed_at', [$from, $to]);
        $summary = (clone $base)->selectRaw('COUNT(*) as orders, COALESCE(SUM(grand_total),0) as revenue, COALESCE(SUM(subtotal),0) as gross, COALESCE(SUM(discount),0) as discount, COALESCE(SUM(tax),0) as tax, COALESCE(SUM(shipping_amount),0) as shipping, COALESCE(AVG(grand_total),0) as aov')->first();
        $items = (int) DB::table('order_items')->whereIn('order_id', (clone $base)->select('id'))->sum('quantity');

        $cancelled = Order::where('status', OrderStatus::Cancelled->value)->whereBetween('placed_at', [$from, $to]);

        // Previous period of equal length, for comparison.
        $days = $from->diffInDays($to) + 1;
        $prevRevenue = (float) Order::revenue()->whereBetween('placed_at', [$from->copy()->subDays((int) ceil($days)), $from->copy()->subSecond()])->sum('grand_total');

        return [
            'summary' => [
                'revenue' => round((float) $summary->revenue, 2),
                'orders' => (int) $summary->orders,
                'items_sold' => $items,
                'gross_sales' => round((float) $summary->gross, 2),
                'discounts' => round((float) $summary->discount, 2),
                'tax' => round((float) $summary->tax, 2),
                'shipping' => round((float) $summary->shipping, 2),
                'average_order_value' => round((float) $summary->aov, 2),
                'cancelled_orders' => (clone $cancelled)->count(),
                'cancelled_value' => round((float) (clone $cancelled)->sum('grand_total'), 2),
                'previous_period_revenue' => round($prevRevenue, 2),
                'growth_percent' => $prevRevenue > 0 ? round(((float) $summary->revenue - $prevRevenue) / $prevRevenue * 100, 1) : null,
            ],
            'series' => $days > 92 ? $this->monthlyBetween($from, $to) : $this->dailySeries($from, $to),
            'by_payment_method' => $this->byPaymentMethod($from, $to),
            'by_status' => Order::whereBetween('placed_at', [$from, $to])->selectRaw('status, COUNT(*) as count, COALESCE(SUM(grand_total),0) as value')
                ->groupBy('status')->toBase()->get()->map(fn ($r) => ['status' => $r->status, 'label' => OrderStatus::from($r->status)->label(), 'count' => (int) $r->count, 'value' => round((float) $r->value, 2)]),
            'top_coupons' => DB::table('coupon_usages')->join('coupons', 'coupons.id', '=', 'coupon_usages.coupon_id')
                ->whereBetween('coupon_usages.created_at', [$from, $to])
                ->groupBy('coupons.code')->selectRaw('coupons.code as code, COUNT(*) as uses, SUM(coupon_usages.discount_amount) as discount')
                ->orderByDesc('uses')->limit(5)->get()->map(fn ($r) => ['code' => $r->code, 'uses' => (int) $r->uses, 'discount' => round((float) $r->discount, 2)]),
        ];
    }

    public function products(Carbon $from, Carbon $to): array
    {
        return [
            'top_products' => $this->topProducts($from, $to, 20),
            'by_category' => $this->topGrouped('categories', $from, $to, 20),
            'by_brand' => $this->topGrouped('brands', $from, $to, 20),
            'most_viewed' => DB::table('products')->whereNull('deleted_at')->orderByDesc('view_count')->limit(10)
                ->get(['id', 'name', 'sku', 'view_count', 'sold_count'])->map(fn ($p) => (array) $p),
            'never_sold' => DB::table('products')->whereNull('deleted_at')->where('is_active', true)
                ->whereNotIn('id', DB::table('order_items')->whereNotNull('product_id')->select('product_id'))->count(),
        ];
    }

    public function customers(Carbon $from, Carbon $to): array
    {
        $revenueStatuses = array_map(fn ($s) => $s->value, OrderStatus::revenueStatuses());
        $buyers = DB::table('orders')->whereIn('status', $revenueStatuses)->whereBetween('placed_at', [$from, $to])
            ->groupBy('user_id')->selectRaw('user_id, COUNT(*) as orders, SUM(grand_total) as spent');
        $buyerStats = DB::query()->fromSub($buyers, 'b')->selectRaw('COUNT(*) as buyers, SUM(CASE WHEN orders > 1 THEN 1 ELSE 0 END) as repeaters')->first();

        return [
            'summary' => [
                'new_customers' => User::customers()->whereBetween('created_at', [$from, $to])->count(),
                'total_customers' => User::customers()->count(),
                'buyers' => (int) ($buyerStats->buyers ?? 0),
                'repeat_buyers' => (int) ($buyerStats->repeaters ?? 0),
                'repeat_rate' => ($buyerStats->buyers ?? 0) > 0 ? round($buyerStats->repeaters / $buyerStats->buyers * 100, 1) : 0,
            ],
            'top_customers' => DB::query()->fromSub($buyers, 'b')->join('users', 'users.id', '=', 'b.user_id')
                ->orderByDesc('spent')->limit(10)->get(['users.id', 'users.name', 'users.email', 'b.orders', 'b.spent'])
                ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'email' => $r->email, 'orders' => (int) $r->orders, 'spent' => round((float) $r->spent, 2)]),
            'signups' => $this->dailyCount('users', 'created_at', $from, $to, fn (QB $q) => $q->whereIn('users.id', DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')->where('roles.name', 'customer')->select('user_id'))),
            'top_cities' => DB::table('orders')->whereBetween('placed_at', [$from, $to])->get(['shipping_address'])
                ->map(fn ($o) => json_decode($o->shipping_address, true)['city'] ?? null)->filter()->countBy()->sortDesc()->take(8)
                ->map(fn ($c, $city) => ['city' => $city, 'orders' => $c])->values(),
        ];
    }

    public function inventory(Carbon $from, Carbon $to): array
    {
        $valuation = DB::table('inventories')->join('products', 'products.id', '=', 'inventories.product_id')->whereNull('products.deleted_at')
            ->selectRaw('SUM(inventories.quantity) as units, SUM(inventories.quantity * products.cost_price) as cost_value, SUM(inventories.quantity * products.price) as retail_value')->first();

        $list = fn ($scope) => Inventory::with('product:id,name,sku')->{$scope}()->orderByRaw('(quantity - reserved) asc')->limit(25)->get()
            ->map(fn (Inventory $i) => ['id' => $i->id, 'sku' => $i->sku, 'product' => $i->product?->name, 'quantity' => $i->quantity, 'reserved' => $i->reserved, 'available' => $i->available(), 'threshold' => $i->low_stock_threshold]);

        return [
            'summary' => [
                'skus' => Inventory::count(),
                'units_on_hand' => (int) ($valuation->units ?? 0),
                'units_reserved' => (int) Inventory::sum('reserved'),
                'stock_value_cost' => round((float) ($valuation->cost_value ?? 0), 2),
                'stock_value_retail' => round((float) ($valuation->retail_value ?? 0), 2),
                'low_stock' => Inventory::lowStock()->count(),
                'out_of_stock' => Inventory::outOfStock()->count(),
            ],
            'movements' => DB::table('inventory_transactions')->whereBetween('created_at', [$from, $to])
                ->groupBy('type')->selectRaw('type, COUNT(*) as entries, SUM(quantity) as units')->get()
                ->map(fn ($r) => ['type' => $r->type, 'label' => ucwords(str_replace('_', ' ', $r->type)), 'entries' => (int) $r->entries, 'units' => (int) $r->units]),
            'low_stock' => $list('lowStock'),
            'out_of_stock' => $list('outOfStock'),
        ];
    }

    // ── helpers ──────────────────────────────────────────────────────────
    private function topProducts(Carbon $from, Carbon $to, int $limit): array
    {
        return DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', array_map(fn ($s) => $s->value, OrderStatus::revenueStatuses()))
            ->whereBetween('orders.placed_at', [$from, $to])
            ->groupBy('order_items.product_id', 'order_items.product_name', 'order_items.sku')
            ->selectRaw('order_items.product_id as id, order_items.product_name as name, order_items.sku as sku, SUM(order_items.quantity) as units, SUM(order_items.line_total) as revenue, COUNT(DISTINCT orders.id) as orders')
            ->orderByDesc('revenue')->limit($limit)->get()
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'sku' => $r->sku, 'units' => (int) $r->units, 'orders' => (int) $r->orders, 'revenue' => round((float) $r->revenue, 2)])->all();
    }

    private function topGrouped(string $table, Carbon $from, Carbon $to, int $limit): array
    {
        $fk = $table === 'categories' ? 'category_id' : 'brand_id';

        return DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join($table, "{$table}.id", '=', "products.{$fk}")
            ->whereIn('orders.status', array_map(fn ($s) => $s->value, OrderStatus::revenueStatuses()))
            ->whereBetween('orders.placed_at', [$from, $to])
            ->groupBy("{$table}.id", "{$table}.name")
            ->selectRaw("{$table}.id as id, {$table}.name as name, SUM(order_items.quantity) as units, SUM(order_items.line_total) as revenue")
            ->orderByDesc('revenue')->limit($limit)->get()
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'units' => (int) $r->units, 'revenue' => round((float) $r->revenue, 2)])->all();
    }

    private function byPaymentMethod(Carbon $from, Carbon $to): array
    {
        return Order::revenue()->whereBetween('placed_at', [$from, $to])
            ->selectRaw('payment_method, COUNT(*) as orders, SUM(grand_total) as revenue')->groupBy('payment_method')->get()
            ->map(fn ($r) => ['method' => $r->payment_method->value, 'label' => $r->payment_method->label(), 'orders' => (int) $r->orders, 'revenue' => round((float) $r->revenue, 2)])->all();
    }

    public function dailySeries(Carbon $from, Carbon $to): array
    {
        $rows = Order::revenue()->whereBetween('placed_at', [$from, $to])
            ->selectRaw('DATE(placed_at) as d, COUNT(*) as orders, SUM(grand_total) as revenue')
            ->groupBy('d')->get()->keyBy(fn ($r) => substr((string) $r->d, 0, 10));

        return collect(CarbonPeriod::create($from->copy()->startOfDay(), '1 day', $to->copy()->startOfDay()))->map(function (Carbon $day) use ($rows) {
            $r = $rows[$day->toDateString()] ?? null;

            return ['date' => $day->toDateString(), 'label' => $day->format('d M'), 'orders' => (int) ($r->orders ?? 0), 'revenue' => round((float) ($r->revenue ?? 0), 2)];
        })->values()->all();
    }

    public function monthlySeries(int $months): array
    {
        return $this->monthlyBetween(now()->subMonthsNoOverflow($months - 1)->startOfMonth(), now()->endOfDay());
    }

    private function monthlyBetween(Carbon $from, Carbon $to): array
    {
        $expr = DB::connection()->getDriverName() === 'sqlite' ? "strftime('%Y-%m', placed_at)" : "DATE_FORMAT(placed_at, '%Y-%m')";
        $rows = Order::revenue()->whereBetween('placed_at', [$from, $to])
            ->selectRaw("{$expr} as m, COUNT(*) as orders, SUM(grand_total) as revenue")->groupBy('m')->get()->keyBy('m');

        return collect(CarbonPeriod::create($from->copy()->startOfMonth(), '1 month', $to->copy()->startOfMonth()))->map(function (Carbon $m) use ($rows) {
            $r = $rows[$m->format('Y-m')] ?? null;

            return ['month' => $m->format('Y-m'), 'label' => $m->format('M Y'), 'orders' => (int) ($r->orders ?? 0), 'revenue' => round((float) ($r->revenue ?? 0), 2)];
        })->values()->all();
    }

    private function dailyCount(string $table, string $column, Carbon $from, Carbon $to, ?\Closure $scope = null): array
    {
        $q = DB::table($table)->whereBetween($column, [$from, $to]);
        if ($scope) {
            $scope($q);
        }
        $rows = $q->selectRaw("DATE({$column}) as d, COUNT(*) as c")->groupBy('d')->pluck('c', 'd');

        return collect(CarbonPeriod::create($from->copy()->startOfDay(), '1 day', $to->copy()->startOfDay()))
            ->map(fn (Carbon $d) => ['date' => $d->toDateString(), 'label' => $d->format('d M'), 'count' => (int) ($rows[$d->toDateString()] ?? 0)])->values()->all();
    }
}
