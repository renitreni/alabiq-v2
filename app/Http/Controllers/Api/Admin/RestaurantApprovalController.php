<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RestaurantApprovalController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    /**
     * Full restaurant roster with owner and approval state.
     */
    public function index(): JsonResponse
    {
        $restaurants = Restaurant::query()
            ->with('owner:id,name,email')
            ->withCount('branches')
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json(['data' => $restaurants]);
    }

    /**
     * Restaurants awaiting approval.
     */
    public function pending(): JsonResponse
    {
        $restaurants = Restaurant::query()
            ->where('is_approved', false)
            ->with('owner:id,name,email')
            ->orderBy('created_at')
            ->paginate(15);

        return response()->json(['data' => $restaurants]);
    }

    /**
     * Approve/suspend or reactivate a restaurant. Admin-only (RestaurantPolicy
     * already allows super admins to update any restaurant).
     */
    public function updateApproval(Request $request, Restaurant $restaurant): JsonResponse
    {
        $this->authorize('update', $restaurant);

        $validated = $request->validate([
            'is_approved' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        $old = $restaurant->only(['is_approved', 'is_active']);
        $restaurant->update([
            'is_approved' => $validated['is_approved'] ?? $restaurant->is_approved,
            'is_active' => $validated['is_active'] ?? $restaurant->is_active,
            'approved_at' => ($validated['is_approved'] ?? $restaurant->is_approved) ? ($restaurant->approved_at ?? now()) : $restaurant->approved_at,
        ]);

        $this->auditLogService->record(Auth::user(), 'restaurant.approval_updated', $restaurant, $old, $restaurant->only(['is_approved', 'is_active']), $request->ip());

        return response()->json(['data' => $restaurant->refresh()->only(['id', 'name', 'slug', 'is_approved', 'is_active', 'approved_at'])]);
    }
}
