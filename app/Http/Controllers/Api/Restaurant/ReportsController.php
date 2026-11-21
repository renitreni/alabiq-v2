<?php

namespace App\Http\Controllers\Api\Restaurant;

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
     * Sales report scoped to the authenticated restaurant only.
     */
    public function sales(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $restaurant = Auth::user()->restaurant;

        if (! $restaurant) {
            abort(403, 'No restaurant linked to this account.');
        }

        return response()->json([
            'data' => $this->reportingService->restaurantSales(
                $restaurant,
                isset($validated['from']) ? Carbon::parse($validated['from']) : null,
                isset($validated['to']) ? Carbon::parse($validated['to']) : null,
            ),
        ]);
    }
}
