<?php

namespace App\Http\Controllers\Api\Order;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Rider;
use App\Services\DispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DispatchController extends Controller
{
    public function __construct(
        private DispatchService $dispatchService
    ) {}

    /**
     * Offer a ready order to a rider (restaurant owner of the order or super admin).
     */
    public function assignRider(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'rider_id' => 'required|integer|exists:riders,id',
        ]);

        $this->authorize('update', $order);

        $rider = Rider::findOrFail($validated['rider_id']);

        try {
            $assignment = $this->dispatchService->assignToOrder($order, $rider, Auth::user());
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'data' => $assignment->load('rider.user:id,name'),
        ], 201);
    }
}
