<?php

namespace App\Http\Controllers\Api\Marketplace;

use App\Http\Controllers\Controller;
use App\Services\MarketplaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketplaceController extends Controller
{
    public function __construct(
        private MarketplaceService $marketplaceService
    ) {}

    public function search(Request $request): JsonResponse
    {
        $filters = $request->only([
            'keyword',
            'cuisine_id',
            'city',
            'max_delivery_fee',
            'min_rating',
            'sort_by',
        ]);

        $perPage = $request->integer('per_page', 12);

        $restaurants = $this->marketplaceService->searchRestaurants($filters, $perPage);

        return response()->json([
            'data' => $restaurants->items(),
            'meta' => [
                'current_page' => $restaurants->currentPage(),
                'last_page' => $restaurants->lastPage(),
                'per_page' => $restaurants->perPage(),
                'total' => $restaurants->total(),
            ],
            'links' => [
                'next' => $restaurants->nextPageUrl(),
                'prev' => $restaurants->previousPageUrl(),
            ],
        ]);
    }

    public function featured(Request $request): JsonResponse
    {
        $limit = $request->integer('limit', 8);
        $restaurants = $this->marketplaceService->getFeaturedRestaurants($limit);

        return response()->json([
            'data' => $restaurants,
        ]);
    }

    public function popularItems(Request $request): JsonResponse
    {
        $limit = $request->integer('limit', 10);
        $items = $this->marketplaceService->getPopularItems($limit);

        return response()->json([
            'data' => $items,
        ]);
    }

    public function cuisines(): JsonResponse
    {
        $cuisines = $this->marketplaceService->getAllCuisines();

        return response()->json([
            'data' => $cuisines,
        ]);
    }

    public function nearby(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'nullable|integer|min:1|max:100',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $restaurants = $this->marketplaceService->getNearbyRestaurants(
            $request->float('latitude'),
            $request->float('longitude'),
            $request->integer('radius', 10),
            $request->integer('limit', 10)
        );

        return response()->json([
            'data' => $restaurants,
        ]);
    }
}
