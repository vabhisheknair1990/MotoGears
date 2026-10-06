<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StaffUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Staff accounts & roles (super admin / users.manage only). */
class StaffController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $page = User::staff()->with('roles.permissions')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))
            ->orderBy('name')->paginate($this->perPage(25));

        return $this->paginated($page, UserResource::class, 'Staff retrieved successfully');
    }

    public function store(StaffUserRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create($request->safe()->only(['name', 'email', 'phone', 'password']));
            $user->forceFill(['is_active' => $request->boolean('is_active', true), 'email_verified_at' => now()])->save();
            $user->roles()->sync(Role::whereIn('name', $request->roles)->pluck('id'));

            return $user;
        });
        $this->audit->log('staff.created', $user, null, ['email' => $user->email, 'roles' => $request->roles]);

        return $this->created(new UserResource($user->load('roles.permissions')), 'Staff member created');
    }

    public function update(StaffUserRequest $request, User $user): JsonResponse
    {
        abort_unless($user->isStaff(), 404);
        if ($user->id === $request->user()->id && $request->has('is_active') && ! $request->boolean('is_active')) {
            return response()->json(['success' => false, 'message' => 'You cannot deactivate your own account.'], 422);
        }
        DB::transaction(function () use ($request, $user) {
            $user->fill($request->safe()->only(['name', 'email', 'phone']));
            if ($request->filled('password')) {
                $user->password = $request->password;
            }
            if ($request->has('is_active')) {
                $user->is_active = $request->boolean('is_active');
            }
            $user->save();
            if ($request->has('roles')) {
                $user->roles()->sync(Role::whereIn('name', $request->roles)->pluck('id'));
            }
        });
        $this->audit->log('staff.updated', $user, null, ['roles' => $request->roles]);

        return $this->ok(new UserResource($user->fresh()->load('roles.permissions')), 'Staff member updated');
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_unless($user->isStaff(), 404);
        if ($user->id === $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'You cannot delete your own account.'], 422);
        }
        if ($user->isSuperAdmin() && User::whereHas('roles', fn ($q) => $q->where('name', Role::SUPER_ADMIN))->count() <= 1) {
            return response()->json(['success' => false, 'message' => 'At least one super admin must remain.'], 422);
        }
        $user->tokens()->delete();
        $user->delete();
        $this->audit->log('staff.deleted', $user);

        return $this->deleted('Staff member removed');
    }

    public function roles(): JsonResponse
    {
        return $this->ok([
            'roles' => Role::with('permissions:id,name')->withCount('users')->orderByDesc('is_staff')->orderBy('id')->get()->map(fn (Role $r) => [
                'id' => $r->id, 'name' => $r->name, 'label' => $r->label, 'description' => $r->description, 'is_staff' => $r->is_staff,
                'users_count' => $r->users_count, 'permissions' => $r->permissions->pluck('name'),
            ]),
            'permissions' => Permission::orderBy('group')->orderBy('name')->get(['id', 'name', 'label', 'group'])->groupBy('group'),
        ], 'Roles retrieved successfully');
    }

    public function updateRole(Request $request, Role $role): JsonResponse
    {
        abort_if($role->name === Role::SUPER_ADMIN, 422, 'Super admin permissions cannot be changed.');
        $data = $request->validate(['permissions' => ['present', 'array'], 'permissions.*' => ['string', 'exists:permissions,name']]);
        $role->permissions()->sync(Permission::whereIn('name', $data['permissions'])->pluck('id'));
        $this->audit->log('role.permissions_updated', $role, null, $data);

        return $this->roles();
    }
}
