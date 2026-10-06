<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCardResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\SearchTerm;
use App\Models\VehicleModel;
use App\Services\ProductQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    public function __construct(private ProductQueryService $products) {}

    /** GET /search?q=thar+led — full results page (paginated). */
    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'sort' => ['sometimes', 'nullable', 'string'],
            'vehicle_variant' => ['sometimes', 'nullable', 'integer'],
            'category' => ['sometimes', 'nullable'],
            'brand' => ['sometimes', 'nullable'],
            'min_price' => ['sometimes', 'nullable', 'numeric'],
            'max_price' => ['sometimes', 'nullable', 'numeric'],
            'in_stock' => ['sometimes', 'nullable'],
            'rating' => ['sometimes', 'nullable', 'numeric'],
        ]);
        $filters = array_merge($data, ['search' => $data['q']]);
        unset($filters['q'], $filters['per_page']);
        if (isset($filters['sort']) && ! array_key_exists($filters['sort'], ProductQueryService::SORTS)) {
            unset($filters['sort']);
        }

        $page = $this->products->query($filters)->paginate($this->perPage(20, 60))->withQueryString();
        $this->record($data['q'], $page->total());

        return $this->paginated($page, ProductCardResource::class, 'Search results', [
            'query' => $data['q'],
            'facets' => $request->boolean('with_facets') ? $this->products->facets($filters) : null,
        ]);
    }

    /** GET /search/suggestions?q=thar — typeahead: products, categories, brands, vehicles. */
    public function suggestions(Request $request): JsonResponse
    {
        $q = trim((string) $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']])['q']);
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';

        $productQuery = $this->products->query(['search' => $q, 'sort' => 'relevance']);
        $products = $productQuery->limit(6)->get();

        return $this->ok([
            'query' => $q,
            'products' => ProductCardResource::collection($products)->resolve($request),
            'categories' => Category::active()->where('name', 'like', $like)->limit(4)->get(['id', 'name', 'slug']),
            'brands' => Brand::active()->where('name', 'like', $like)->limit(4)->get(['id', 'name', 'slug']),
            'vehicles' => VehicleModel::active()->with('manufacturer:id,name')
                ->where(fn ($w) => $w->where('name', 'like', $like)->orWhereHas('manufacturer', fn ($m) => $m->where('name', 'like', $like)))
                ->limit(4)->get()->map(fn ($m) => ['id' => $m->id, 'name' => $m->manufacturer->name.' '.$m->name, 'manufacturer_id' => $m->vehicle_manufacturer_id]),
            'terms' => SearchTerm::where('term', 'like', mb_strtolower($q).'%')->where('results', '>', 0)->orderByDesc('hits')->limit(5)->pluck('term'),
        ], 'Suggestions retrieved');
    }

    public function popular(): JsonResponse
    {
        $terms = SearchTerm::where('results', '>', 0)->orderByDesc('hits')->limit(10)->pluck('term');

        return $this->ok($terms, 'Popular searches retrieved');
    }

    private function record(string $term, int $results): void
    {
        $term = mb_strtolower(trim(mb_substr($term, 0, 100)));
        if (mb_strlen($term) < 2) {
            return;
        }
        $updated = SearchTerm::where('term', $term)->update(['hits' => DB::raw('hits + 1'), 'results' => $results, 'updated_at' => now()]);
        if (! $updated) {
            SearchTerm::firstOrCreate(['term' => $term], ['hits' => 1, 'results' => $results]);
        }
    }
}
