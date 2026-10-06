<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ChangePasswordRequest;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Http\Resources\CustomerVehicleResource;
use App\Http\Resources\NotificationResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return $this->ok(new UserResource($request->user()->load('roles.permissions')), 'Profile retrieved successfully');
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return $this->ok(new UserResource($user->load('roles.permissions')), 'Profile updated');
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill(['password' => Hash::make($request->password)])->save();
        // Sign out every other session.
        $user->tokens()->where('id', '!=', $user->currentAccessToken()?->id)->delete();

        return $this->ok(null, 'Password changed successfully');
    }

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $orders = $user->orders();

        return $this->ok([
            'user' => (new UserResource($user->load('roles.permissions')))->resolve($request),
            'stats' => [
                'orders' => (clone $orders)->count(),
                'open_orders' => (clone $orders)->whereNotIn('status', [OrderStatus::Delivered->value, OrderStatus::Cancelled->value, OrderStatus::Returned->value, OrderStatus::Refunded->value])->count(),
                'total_spent' => round((float) (clone $orders)->revenue()->sum('grand_total'), 2),
                'wishlist' => $user->wishlist?->items()->count() ?? 0,
                'vehicles' => $user->vehicles()->count(),
                'reviews' => $user->reviews()->count(),
                'unread_notifications' => $user->unreadNotifications()->count(),
            ],
            'recent_orders' => OrderResource::collection($user->orders()->with('items')->withCount('items')->latest()->limit(3)->get())->resolve($request),
            'default_vehicle' => ($v = $user->vehicles()->with('variant.model.manufacturer')->where('is_default', true)->first())
                ? (new CustomerVehicleResource($v))->resolve($request) : null,
        ], 'Dashboard retrieved');
    }

    public function notifications(Request $request): JsonResponse
    {
        $page = $request->user()->notifications()->paginate($this->perPage(15));

        return $this->paginated($page, NotificationResource::class, 'Notifications retrieved', [
            'unread' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markNotificationsRead(Request $request): JsonResponse
    {
        $ids = (array) $request->input('ids', []);
        $q = $request->user()->unreadNotifications();
        if ($ids) {
            $q->whereIn('id', $ids);
        }
        $q->update(['read_at' => now()]);

        return $this->ok(null, 'Notifications marked as read');
    }
}
