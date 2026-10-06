<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\InventoryTransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InventoryAdjustRequest;
use App\Http\Resources\InventoryResource;
use App\Http\Resources\InventoryTransactionResource;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Services\AuditLogger;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function __construct(private InventoryService $inventory, private AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['in_stock', 'low_stock', 'out_of_stock'])],
            'sort' => ['nullable', Rule::in(['available_asc', 'available_desc', 'sku', 'updated'])],
        ]);
        $q = Inventory::with(['product' => fn ($p) => $p->withTrashed()->with(['primaryImage', 'brand:id,name'])])
            ->whereHas('product')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('sku', 'like', "%{$s}%")->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$s}%"))));
        match ($request->status) {
            'low_stock' => $q->lowStock(),
            'out_of_stock' => $q->outOfStock(),
            'in_stock' => $q->whereRaw('(quantity - reserved) > low_stock_threshold'),
            default => null,
        };
        match ($request->sort) {
            'available_desc' => $q->orderByRaw('(quantity - reserved) desc'),
            'sku' => $q->orderBy('sku'),
            'updated' => $q->latest('updated_at'),
            default => $q->orderByRaw('(quantity - reserved) asc')->orderBy('id'),
        };

        return $this->paginated($q->paginate($this->perPage(25)), InventoryResource::class, 'Inventory retrieved successfully', [
            'counts' => [
                'all' => Inventory::count(),
                'low_stock' => Inventory::lowStock()->count(),
                'out_of_stock' => Inventory::outOfStock()->count(),
            ],
        ]);
    }

    public function show(Request $request, Inventory $inventory): JsonResponse
    {
        $inventory->load(['product' => fn ($p) => $p->withTrashed()->with(['primaryImage', 'brand:id,name'])]);
        $history = $inventory->transactions()->with(['user:id,name', 'product:id,name,sku'])->paginate($this->perPage(25));

        return $this->ok([
            'inventory' => (new InventoryResource($inventory))->resolve($request),
            'history' => InventoryTransactionResource::collection($history->getCollection())->resolve($request),
            'history_meta' => ['current_page' => $history->currentPage(), 'last_page' => $history->lastPage(), 'per_page' => $history->perPage(), 'total' => $history->total()],
            'adjustment_types' => array_map(fn ($t) => ['value' => $t->value, 'label' => $t->label()], InventoryTransactionType::manualTypes()),
        ], 'Inventory retrieved successfully');
    }

    public function update(Request $request, Inventory $inventory): JsonResponse
    {
        $data = $request->validate([
            'low_stock_threshold' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'allow_backorder' => ['sometimes', 'boolean'],
            'location' => ['sometimes', 'nullable', 'string', 'max:60'],
        ]);
        $inventory->update($data);
        $this->audit->changes('inventory.updated', $inventory);

        return $this->ok(new InventoryResource($inventory->load('product')), 'Inventory settings updated');
    }

    public function adjust(InventoryAdjustRequest $request, Inventory $inventory): JsonResponse
    {
        $updated = $this->inventory->adjust(
            $inventory,
            InventoryTransactionType::from($request->type),
            (int) $request->quantity,
            $request->note,
            $request->reference,
            $request->user(),
        );

        return $this->ok(new InventoryResource($updated->load('product')), 'Stock updated');
    }

    public function transactions(Request $request): JsonResponse
    {
        $request->validate(['type' => ['nullable', 'string'], 'product_id' => ['nullable', 'integer'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $page = InventoryTransaction::with(['user:id,name', 'product:id,name,sku'])
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->product_id, fn ($q, $id) => $q->where('product_id', $id))
            ->when($request->from, fn ($q, $d) => $q->where('created_at', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->where('created_at', '<=', \Carbon\Carbon::parse($d)->endOfDay()))
            ->latest('id')->paginate($this->perPage(30));

        return $this->paginated($page, InventoryTransactionResource::class, 'Inventory history retrieved');
    }
}
