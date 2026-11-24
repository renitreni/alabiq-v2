<?php

namespace App\Services;

use App\Models\Restaurant;

class MarketplaceService
{
    public function __construct(
        private ?CoverageService $coverageService = null
    ) {}

    /**
     * Lazily resolve the coverage service so direct `new MarketplaceService`
     * call sites keep working.
     */
    private function coverage(): CoverageService
    {
        return $this->coverageService ??= new CoverageService;
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
                'delivery_fee' => (float) $branch->delivery_fee,
                'minimum_order' => (float) $branch->minimum_order,
                'estimated_delivery_time' => $branch->estimated_delivery_time,
                'opens_at' => $branch->opens_at?->format('H:i'),
                'closes_at' => $branch->closes_at?->format('H:i'),
                'is_open' => $branch->isOpen(),
                'coordinates' => $branch->coordinates,
            ] : null,
            'categories' => $categories,
        ];
    }

    /**
     * Delivery coverage check — delegates to CoverageService.
     *
     * @return array{covered: bool, in_custom_zone: bool, delivery_fee: float, minimum_order: float, estimated_delivery_time: int, zone_name: string|null, branch_id: int}
     */
    public function checkDeliveryCoverage(int $branchId, float $latitude, float $longitude): array
    {
        return $this->coverage()->checkCoverage($branchId, $latitude, $longitude);
    }
}
