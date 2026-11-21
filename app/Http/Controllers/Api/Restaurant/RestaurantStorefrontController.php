<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Http\Controllers\Controller;
use App\Services\MarketplaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RestaurantStorefrontController extends Controller
{
    public function __construct(
        private MarketplaceService $marketplaceService
    ) {}

    public function show(Request $request, string $slug): JsonResponse
    {
        $branchId = $request->integer('branch_id');

        $data = $this->marketplaceService->getRestaurantWithMenu($slug, $branchId ?: null);

        if (! $data) {
            return response()->json([
                'message' => 'Restaurant not found',
            ], 404);
        }

        return response()->json([
            'data' => $data,
        ]);
    }

    public function checkCoverage(Request $request, string $slug): JsonResponse
    {
        $request->validate([
            'branch_id' => 'required|integer|exists:restaurant_branches,id',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $result = $this->marketplaceService->checkDeliveryCoverage(
            $request->integer('branch_id'),
            $request->float('latitude'),
            $request->float('longitude')
        );

        return response()->json([
            'data' => $result,
        ]);
    }
}
