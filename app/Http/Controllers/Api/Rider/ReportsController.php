<?php

namespace App\Http\Controllers\Api\Rider;

use App\Http\Controllers\Controller;
use App\Services\ReportingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportsController extends Controller
{
    public function __construct(
        private ReportingService $reportingService
    ) {}

    /**
     * Earnings summary scoped to the authenticated rider only.
     */
    public function earnings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $rider = Auth::user()->rider;

        if (! $rider) {
            abort(403, 'No rider profile linked to this account.');
        }

        return response()->json([
            'data' => $this->reportingService->riderEarnings(
                $rider,
                isset($validated['from']) ? Carbon::parse($validated['from']) : null,
                isset($validated['to']) ? Carbon::parse($validated['to']) : null,
            ),
        ]);
    }

    /**
     * Paginated delivery history for the authenticated rider.
     */
    public function deliveries(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
        ]);

        $rider = Auth::user()->rider;

        if (! $rider) {
            abort(403, 'No rider profile linked to this account.');
        }

        return response()->json([
            'data' => $this->reportingService->riderDeliveryHistory(
                $rider,
                $validated['page'] ?? 1
            ),
        ]);
    }
}
