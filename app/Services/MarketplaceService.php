<?php

namespace App\Services;

use App\Models\Cuisine;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\RestaurantBranch;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class MarketplaceService
{
    public function searchRestaurants(array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        $query = Restaurant::query()
            ->active()
            ->approved()
            ->acceptingOrders()
            ->with(['cuisines', 'activeBranches.deliveryZones', 'menuItems.category'])
            ->withCount(['menuItems as items_count' => fn ($q) => $q->available()]);

        if (! empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%")
                    ->orWhereHas('cuisines', fn ($cq) => $cq->where('name', 'like', "%{$keyword}%"));
            });
        }

        if (! empty($filters['cuisine_id'])) {
            $query->whereHas('cuisines', fn ($q) => $q->where('cuisines.id', $filters['cuisine_id']));
        }

        if (! empty($filters['city'])) {
            $query->inCity($filters['city']);
        }

        if (isset($filters['max_delivery_fee']) && is_numeric($filters['max_delivery_fee'])) {
            $query->whereHas('activeBranches', fn ($q) => $q->where('delivery_fee', '<=', $filters['max_delivery_fee']));
        }

        $sortBy = $filters['sort_by'] ?? 'relevance';
        switch ($sortBy) {
            case 'rating':
                $query->orderByDesc('created_at');
                break;
            case 'delivery_time':
                $query->whereHas('activeBranches', fn ($q) => $q->orderBy('estimated_delivery_time'));
                break;
            case 'delivery_fee':
                $query->whereHas('activeBranches', fn ($q) => $q->orderBy('delivery_fee'));
                break;
            case 'newest':
                $query->orderByDesc('created_at');
                break;
            default:
                $query->orderByDesc('created_at');
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function getRestaurantWithMenu(string $slug, ?int $branchId = null): ?array
    {
        $restaurant = Restaurant::query()
            ->active()
            ->approved()
            ->acceptingOrders()
            ->with([
                'cuisines',
                'activeBranches.deliveryZones',
                'menuCategories' => fn ($q) => $q->active()->with([
                    'menuItems' => fn ($q) => $q->available()
                        ->with(['options.values' => fn ($q) => $q->active()])
                        ->orderBy('sort_order'),
                ])->orderBy('sort_order'),
            ])
            ->where('slug', $slug)
            ->first();

        if (! $restaurant) {
            return null;
        }

        $branch = null;
        if ($branchId) {
            $branch = $restaurant->activeBranches->firstWhere('id', $branchId);
        } elseif ($restaurant->activeBranches->isNotEmpty()) {
            $branch = $restaurant->activeBranches->first();
        }

        $categories = $restaurant->menuCategories->map(function ($category) use ($branch) {
            $items = $category->menuItems->filter(function ($item) use ($branch) {
                if (! $branch) {
                    return true;
                }

                return $item->isAvailableAtBranch($branch->id);
            })->map(function ($item) use ($branch) {
                $item->branch_price = $branch ? $item->getPriceAtBranch($branch->id) : $item->price;

                return $item;
            })->values();

            return [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'image' => $category->image,
                'items' => $items,
            ];
        })->filter(fn ($cat) => $cat['items']->isNotEmpty())->values();

        return [
            'restaurant' => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'slug' => $restaurant->slug,
                'description' => $restaurant->description,
                'logo' => $restaurant->logo,
                'cover_image' => $restaurant->cover_image,
                'phone' => $restaurant->phone,
                'email' => $restaurant->email,
                'address' => $restaurant->address,
                'city' => $restaurant->city,
                'district' => $restaurant->district,
                'commission_rate' => $restaurant->commission_rate,
                'estimated_delivery_time' => $restaurant->estimated_delivery_time,
                'cuisines' => $restaurant->cuisines->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug, 'icon' => $c->icon]),
            ],
            'branch' => $branch ? [
                'id' => $branch->id,
                'name' => $branch->name,
                'slug' => $branch->slug,
                'address' => $branch->address,
                'phone' => $branch->phone,
                'delivery_fee' => $branch->delivery_fee,
                'minimum_order' => $branch->minimum_order,
                'estimated_delivery_time' => $branch->estimated_delivery_time,
                'opens_at' => $branch->opens_at?->format('H:i'),
                'closes_at' => $branch->closes_at?->format('H:i'),
                'is_open' => $branch->isOpen(),
                'coordinates' => $branch->coordinates,
            ] : null,
            'categories' => $categories,
        ];
    }

    public function getFeaturedRestaurants(int $limit = 8): Collection
    {
        return Restaurant::query()
            ->active()
            ->approved()
            ->acceptingOrders()
            ->with(['cuisines', 'activeBranches'])
            ->inRandomOrder()
            ->limit($limit)
            ->get();
    }

    public function getPopularItems(int $limit = 10): Collection
    {
        return MenuItem::query()
            ->available()
            ->popular()
            ->with(['restaurant.cuisines', 'category', 'options.values'])
            ->inRandomOrder()
            ->limit($limit)
            ->get();
    }

    public function getAllCuisines(): Collection
    {
        return Cuisine::active()->get(['id', 'name', 'slug', 'icon', 'image']);
    }

    public function checkDeliveryCoverage(int $branchId, float $latitude, float $longitude): array
    {
        $branch = RestaurantBranch::query()
            ->with('deliveryZones')
            ->find($branchId);

        if (! $branch) {
            return [
                'covered' => false,
                'delivery_fee' => 0,
                'minimum_order' => 0,
                'estimated_delivery_time' => 0,
                'message' => 'Restaurant branch not found',
            ];
        }

        foreach ($branch->deliveryZones as $zone) {
            if ($zone->containsPoint($latitude, $longitude)) {
                return [
                    'covered' => true,
                    'delivery_fee' => (float) $zone->delivery_fee,
                    'minimum_order' => (float) $zone->minimum_order,
                    'estimated_delivery_time' => (int) $zone->estimated_delivery_time,
                    'zone_name' => $zone->name,
                ];
            }
        }

        return [
            'covered' => true,
            'delivery_fee' => (float) $branch->delivery_fee,
            'minimum_order' => (float) $branch->minimum_order,
            'estimated_delivery_time' => (int) $branch->estimated_delivery_time,
            'zone_name' => 'Standard Delivery',
        ];
    }

    public function getNearbyRestaurants(float $latitude, float $longitude, int $radiusKm = 10, int $limit = 10): Collection
    {
        $latRange = $radiusKm / 111.0;
        $lngRange = $radiusKm / (111.0 * cos(deg2rad($latitude)));

        return Restaurant::query()
            ->active()
            ->approved()
            ->acceptingOrders()
            ->whereHas('activeBranches', function ($q) use ($latitude, $longitude, $latRange, $lngRange) {
                $q->whereBetween('latitude', [$latitude - $latRange, $latitude + $latRange])
                    ->whereBetween('longitude', [$longitude - $lngRange, $longitude + $lngRange]);
            })
            ->with(['cuisines', 'activeBranches'])
            ->limit($limit)
            ->get();
    }
}
