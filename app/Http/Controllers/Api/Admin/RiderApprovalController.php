<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rider;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RiderApprovalController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    /**
     * Rider roster with availability summary.
     */
    public function index(): JsonResponse
    {
        $riders = Rider::query()
            ->with('user:id,name,email')
            ->withCount('assignments')
            ->orderByDesc('id')
            ->paginate(15);

        return response()->json(['data' => $riders]);
    }

    /**
     * Activate or deactivate a rider account.
     */
    public function updateApproval(Request $request, Rider $rider): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $old = ['is_active' => $rider->is_active];
        $rider->update(['is_active' => $validated['is_active']]);

        $this->auditLogService->record(Auth::user(), 'rider.activation_updated', $rider, $old, [
            'is_active' => $validated['is_active'],
        ], $request->ip());

        return response()->json(['data' => $rider->refresh()->only(['id', 'is_active', 'is_online', 'vehicle_type'])]);
    }
}
