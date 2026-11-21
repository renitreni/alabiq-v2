<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformCommission;
use App\Models\Restaurant;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommissionController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    /**
     * Global default plus per-restaurant overrides.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                'global_rate' => round((float) config('alabiq.platform_commission_rate', 0.15) * 100, 2),
                'overrides' => PlatformCommission::query()
                    ->with('restaurant:id,name')
                    ->orderBy('restaurant_id')
                    ->get(),
            ],
        ]);
    }

    /**
     * Create or update a restaurant-specific commission override.
     */
    public function upsert(Request $request, Restaurant $restaurant): JsonResponse
    {
        $validated = $request->validate([
            'rate' => 'required|numeric|min:0|max:100',
            'is_active' => 'sometimes|boolean',
        ]);

        $existing = PlatformCommission::query()
            ->where('restaurant_id', $restaurant->id)
            ->first();

        $old = $existing?->only(['rate', 'is_active']);

        $commission = PlatformCommission::query()->updateOrCreate(
            ['restaurant_id' => $restaurant->id],
            [
                'rate' => $validated['rate'],
                'is_active' => $validated['is_active'] ?? true,
            ]
        );

        $this->auditLogService->record(Auth::user(), 'commission.updated', $commission, $old, [
            'restaurant_id' => $restaurant->id,
            'rate' => $commission->rate,
            'is_active' => $commission->is_active,
        ], $request->ip());

        return response()->json(['data' => $commission->load('restaurant:id,name')]);
    }
}
