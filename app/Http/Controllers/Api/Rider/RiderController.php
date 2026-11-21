<?php

namespace App\Http\Controllers\Api\Rider;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAssignment;
use App\Models\Rider;
use App\Models\RiderAvailability;
use App\Services\DispatchService;
use App\Services\RiderLocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RiderController extends Controller
{
    public function __construct(
        private RiderLocationService $locationService,
        private DispatchService $dispatchService,
    ) {}

    /**
     * Toggle availability (online / busy / offline).
     */
    public function updateAvailability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:online,busy,offline',
        ]);

        $rider = $this->currentRider();
        $this->authorize('updateAvailability', $rider);

        $status = $validated['status'];

        $rider->update(['is_online' => $status !== RiderAvailability::STATUS_OFFLINE]);

        RiderAvailability::create([
            'rider_id' => $rider->id,
            'status' => $status,
            'note' => $request->input('note'),
        ]);

        return response()->json([
            'data' => [
                'id' => $rider->id,
                'is_online' => $rider->is_online,
                'status' => $status,
            ],
        ]);
    }

    /**
     * Ingest a GPS location update (throttled server-side by movement).
     */
    public function updateLocation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'speed' => 'nullable|numeric|min:0',
            'heading' => 'nullable|numeric|between:0,360',
            'accuracy' => 'nullable|numeric|min:0',
        ]);

        $rider = $this->currentRider();
        $this->authorize('updateLocation', $rider);

        $result = $this->locationService->update(
            $rider,
            (float) $validated['latitude'],
            (float) $validated['longitude'],
            [
                'speed' => $validated['speed'] ?? null,
                'heading' => $validated['heading'] ?? null,
                'accuracy' => $validated['accuracy'] ?? null,
            ]
        );

        return response()->json([
            'data' => [
                'persisted' => $result['persisted'],
                'moved_m' => round($result['moved_m'], 2),
                'location' => $result['rider_location'],
            ],
        ]);
    }

    /**
     * The rider's own assignment history, newest first.
     */
    public function assignments(): JsonResponse
    {
        $rider = $this->currentRider();

        $assignments = DeliveryAssignment::query()
            ->where('rider_id', $rider->id)
            ->with(['order:id,status,total_amount,branch_id,restaurant_id', 'order.restaurant:id,name'])
            ->orderByDesc('offered_at')
            ->paginate(15);

        return response()->json(['data' => $assignments]);
    }

    /**
     * Accept an offered assignment.
     */
    public function accept(DeliveryAssignment $assignment): JsonResponse
    {
        $rider = $this->currentRider();

        try {
            $assignment = $this->dispatchService->accept($assignment, $rider);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['data' => $assignment]);
    }

    /**
     * Reject an offered assignment (order stays rider_assigned).
     */
    public function reject(Request $request, DeliveryAssignment $assignment): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $rider = $this->currentRider();

        try {
            $assignment = $this->dispatchService->reject(
                $assignment,
                $rider,
                $validated['reason'] ?? 'No reason given'
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['data' => $assignment]);
    }

    /**
     * Resolve the authenticated user's rider profile (403 without one).
     */
    private function currentRider(): Rider
    {
        $rider = Auth::user()->rider;

        if (! $rider) {
            abort(403, 'No rider profile linked to this account.');
        }

        return $rider;
    }
}
