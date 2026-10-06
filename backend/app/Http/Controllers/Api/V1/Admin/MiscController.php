<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Http\Resources\NotificationResource;
use App\Http\Resources\UserResource;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Review;
use App\Models\VehicleManufacturer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MiscController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        return $this->ok(array_merge((new UserResource($request->user()->load('roles.permissions')))->resolve($request), [
            'badges' => [
                'pending_reviews' => Review::where('status', 'pending')->count(),
                'new_enquiries' => ContactMessage::where('status', 'new')->count(),
                'open_orders' => \App\Models\Order::whereIn('status', ['pending', 'confirmed'])->count(),
                'unread_notifications' => $request->user()->unreadNotifications()->count(),
            ],
        ]), 'Admin profile retrieved');
    }

    /** Dropdown data for admin forms in one call. */
    public function lookups(): JsonResponse
    {
        return $this->ok([
            'categories' => Category::orderByRaw('COALESCE(parent_id, id)')->orderByRaw('parent_id IS NOT NULL')->orderBy('sort_order')
                ->get(['id', 'name', 'parent_id', 'slug', 'is_active']),
            'brands' => Brand::orderBy('name')->get(['id', 'name', 'slug', 'is_active']),
            'manufacturers' => VehicleManufacturer::orderBy('name')->get(['id', 'name', 'vehicle_type']),
            'attributes' => Attribute::with('values:id,attribute_id,value,slug')->orderBy('sort_order')->get(['id', 'name', 'slug']),
            'order_statuses' => OrderStatus::options(),
            'payment_methods' => array_map(fn ($m) => ['value' => $m->value, 'label' => $m->label()], PaymentMethod::cases()),
            'tax_rates' => [0, 5, 12, 18, 28],
            'vehicle_types' => ['car', 'motorcycle', 'universal'],
            'fuel_types' => ['Petrol', 'Diesel', 'CNG', 'Electric', 'Hybrid', 'LPG'],
            'transmissions' => ['MT', 'AT', 'AMT', 'CVT', 'DCT', 'IVT', 'Single-speed'],
            'positions' => ['Front', 'Rear', 'Front Left', 'Front Right', 'Rear Left', 'Rear Right', 'Universal'],
        ], 'Lookups retrieved');
    }

    public function auditLogs(Request $request): JsonResponse
    {
        $request->validate(['action' => ['nullable', 'string', 'max:60'], 'user_id' => ['nullable', 'integer']]);
        $page = AuditLog::with('user:id,name')
            ->when($request->action, fn ($q, $a) => $q->where('action', 'like', $a.'%'))
            ->when($request->user_id, fn ($q, $id) => $q->where('user_id', $id))
            ->latest('id')->paginate($this->perPage(30));

        return $this->paginated($page, AuditLogResource::class, 'Audit log retrieved');
    }

    public function notifications(Request $request): JsonResponse
    {
        $page = $request->user()->notifications()->paginate($this->perPage(20));

        return $this->paginated($page, NotificationResource::class, 'Notifications retrieved', ['unread' => $request->user()->unreadNotifications()->count()]);
    }

    public function readNotifications(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->when($request->ids, fn ($q, $ids) => $q->whereIn('id', (array) $ids))->update(['read_at' => now()]);

        return $this->ok(null, 'Notifications marked as read');
    }

    // ── Attributes (filterable specs such as Position, Colour Temperature) ──
    public function attributes(): JsonResponse
    {
        return $this->ok(Attribute::with('values')->orderBy('sort_order')->get(), 'Attributes retrieved');
    }

    public function storeAttribute(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:attributes,name'],
            'is_filterable' => ['sometimes', 'boolean'],
            'values' => ['nullable', 'array'], 'values.*' => ['string', 'max:100'],
        ]);
        $attr = Attribute::create(['name' => $data['name'], 'slug' => Str::slug($data['name']), 'is_filterable' => $data['is_filterable'] ?? true]);
        foreach ($data['values'] ?? [] as $i => $v) {
            $attr->values()->firstOrCreate(['slug' => Str::slug($v)], ['value' => $v, 'sort_order' => $i]);
        }

        return $this->created($attr->load('values'), 'Attribute created');
    }

    public function updateAttribute(Request $request, Attribute $attribute): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100', 'unique:attributes,name,'.$attribute->id],
            'is_filterable' => ['sometimes', 'boolean'],
            'values' => ['sometimes', 'array'], 'values.*' => ['string', 'max:100'],
        ]);
        $attribute->update(array_filter(['name' => $data['name'] ?? null, 'is_filterable' => $data['is_filterable'] ?? null], fn ($v) => $v !== null));
        if (isset($data['values'])) {
            $keep = [];
            foreach ($data['values'] as $i => $v) {
                $keep[] = $attribute->values()->updateOrCreate(['slug' => Str::slug($v)], ['value' => $v, 'sort_order' => $i])->id;
            }
            AttributeValue::where('attribute_id', $attribute->id)->whereNotIn('id', $keep)->delete();
        }

        return $this->ok($attribute->load('values'), 'Attribute updated');
    }

    public function destroyAttribute(Attribute $attribute): JsonResponse
    {
        $attribute->delete();

        return $this->deleted('Attribute deleted');
    }
}
