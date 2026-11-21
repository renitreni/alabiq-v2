<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    /**
     * Paginated user list with optional role filter.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with('restaurant:id,owner_id,name')
            ->when($request->query('role'), fn ($q, $role) => $q->where('role', $role))
            ->when($request->query('search'), function ($q, $search) {
                $q->where(fn ($inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json(['data' => $users]);
    }

    /**
     * Change a user's role (never the admin's own).
     */
    public function updateRole(Request $request, User $user): JsonResponse
    {
        $this->authorize('updateRole', $user);

        $validated = $request->validate([
            'role' => ['required', Rule::in([
                User::ROLE_CUSTOMER,
                User::ROLE_RESTAURANT,
                User::ROLE_RIDER,
                User::ROLE_SUPER_ADMIN,
            ])],
        ]);

        $old = ['role' => $user->role];
        $user->update(['role' => $validated['role']]);

        $this->auditLogService->record(Auth::user(), 'user.role_updated', $user, $old, [
            'role' => $validated['role'],
        ], $request->ip());

        return response()->json(['data' => $user->only(['id', 'name', 'email', 'role'])]);
    }
}
