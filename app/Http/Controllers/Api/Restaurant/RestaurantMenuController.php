<?php

namespace App\Http\Controllers\Api\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RestaurantMenuController extends Controller
{
    /**
     * List menu categories and items for the authenticated restaurant.
     */
    public function index(Request $request): JsonResponse
    {
        $restaurant = $this->restaurant($request);

        return response()->json([
            'data' => [
                'items' => MenuItem::forRestaurant($restaurant->id)
                    ->with('category')
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(),
                'categories' => MenuCategory::forRestaurant($restaurant->id)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['id', 'name', 'slug', 'sort_order', 'is_active']),
            ],
        ]);
    }

    /**
     * Store a new menu item, optionally creating a category by name.
     */
    public function store(Request $request): JsonResponse
    {
        $restaurant = $this->restaurant($request);
        $validated = $this->validateItem($request, $restaurant, true);

        $category = ($validated['menu_category_id'] ?? null)
            ? $this->category($restaurant, (int) $validated['menu_category_id'])
            : $this->categoryFromName($restaurant, $validated['category_name'] ?? null);

        $item = MenuItem::create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id,
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($restaurant, $validated['name']),
            'description' => $validated['description'] ?? null,
            'image' => $validated['image'] ?? null,
            'price' => $validated['price'],
            'compare_at_price' => $validated['compare_at_price'] ?? null,
            'calories' => $validated['calories'] ?? null,
            'preparation_time' => $validated['preparation_time'] ?? null,
            'is_vegetarian' => $request->boolean('is_vegetarian'),
            'is_vegan' => $request->boolean('is_vegan'),
            'is_gluten_free' => $request->boolean('is_gluten_free'),
            'is_spicy' => $request->boolean('is_spicy'),
            'is_popular' => $request->boolean('is_popular'),
            'is_available' => $request->boolean('is_available', true),
            'sort_order' => $validated['sort_order'] ?? 0,
            'metadata' => $validated['metadata'] ?? null,
        ]);

        return response()->json([
            'data' => $item->load('category'),
        ], 201);
    }

    /**
     * Show a menu item owned by the restaurant.
     */
    public function show(Request $request, MenuItem $menuItem): JsonResponse
    {
        $restaurant = $this->restaurant($request);

        return response()->json([
            'data' => $this->item($restaurant, $menuItem)->load('category'),
        ]);
    }

    /**
     * Update a menu item owned by the restaurant.
     */
    public function update(Request $request, MenuItem $menuItem): JsonResponse
    {
        $restaurant = $this->restaurant($request);
        $item = $this->item($restaurant, $menuItem);
        $validated = $this->validateItem($request, $restaurant, false);
        $category = $this->category($restaurant, (int) $validated['menu_category_id']);

        $item->update([
            'menu_category_id' => $category->id,
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($restaurant, $validated['name'], $item->id),
            'description' => $validated['description'] ?? null,
            'image' => $validated['image'] ?? null,
            'price' => $validated['price'],
            'compare_at_price' => $validated['compare_at_price'] ?? null,
            'calories' => $validated['calories'] ?? null,
            'preparation_time' => $validated['preparation_time'] ?? null,
            'is_vegetarian' => $request->boolean('is_vegetarian'),
            'is_vegan' => $request->boolean('is_vegan'),
            'is_gluten_free' => $request->boolean('is_gluten_free'),
            'is_spicy' => $request->boolean('is_spicy'),
            'is_popular' => $request->boolean('is_popular'),
            'is_available' => $request->boolean('is_available'),
            'sort_order' => $validated['sort_order'] ?? 0,
            'metadata' => $validated['metadata'] ?? null,
        ]);

        return response()->json([
            'data' => $item->refresh()->load('category'),
        ]);
    }

    /**
     * Soft-delete a menu item owned by the restaurant.
     */
    public function destroy(Request $request, MenuItem $menuItem): JsonResponse
    {
        $restaurant = $this->restaurant($request);

        $this->item($restaurant, $menuItem)->delete();

        return response()->json([
            'message' => 'Menu item deleted',
        ]);
    }

    /**
     * Resolve the authenticated user's restaurant.
     */
    private function restaurant(Request $request): Restaurant
    {
        $restaurant = $request->user()?->restaurant;

        if (! $restaurant) {
            abort(403, 'No restaurant linked to this account.');
        }

        return $restaurant;
    }

    /**
     * Resolve a menu item only when it belongs to the restaurant.
     */
    private function item(Restaurant $restaurant, MenuItem $menuItem): MenuItem
    {
        if ($menuItem->restaurant_id !== $restaurant->id) {
            abort(404, 'Menu item not found.');
        }

        return $menuItem;
    }

    /**
     * Resolve a category only when it belongs to the restaurant.
     */
    private function category(Restaurant $restaurant, int $categoryId): MenuCategory
    {
        return MenuCategory::forRestaurant($restaurant->id)->findOrFail($categoryId);
    }

    /**
     * Find or create a category from a display name.
     */
    private function categoryFromName(Restaurant $restaurant, ?string $name): MenuCategory
    {
        $name = trim((string) $name);

        if ($name === '') {
            abort(422, 'A menu category is required.');
        }

        $slug = Str::slug($name) ?: 'category';
        $nextSortOrder = ((int) MenuCategory::forRestaurant($restaurant->id)->max('sort_order')) + 1;

        return MenuCategory::firstOrCreate(
            ['restaurant_id' => $restaurant->id, 'slug' => $slug],
            ['name' => $name, 'sort_order' => $nextSortOrder, 'is_active' => true]
        );
    }

    /**
     * Shared validation for menu item create/update requests.
     */
    private function validateItem(Request $request, Restaurant $restaurant, bool $creating): array
    {
        return $request->validate([
            'menu_category_id' => [
                $creating ? 'required_without:category_name' : 'required',
                'nullable',
                'integer',
                Rule::exists('menu_categories', 'id')->where(fn ($query) => $query->where('restaurant_id', $restaurant->id)),
            ],
            'category_name' => [$creating ? 'nullable' : 'prohibited', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'url', 'max:2048'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'calories' => ['nullable', 'integer', 'min:0'],
            'preparation_time' => ['nullable', 'string', 'max:50'],
            'is_vegetarian' => ['nullable', 'boolean'],
            'is_vegan' => ['nullable', 'boolean'],
            'is_gluten_free' => ['nullable', 'boolean'],
            'is_spicy' => ['nullable', 'boolean'],
            'is_popular' => ['nullable', 'boolean'],
            'is_available' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
        ]);
    }

    /**
     * Generate a restaurant-unique slug.
     */
    private function uniqueSlug(Restaurant $restaurant, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'menu-item';
        $slug = $base;
        $suffix = 2;

        while (
            MenuItem::forRestaurant($restaurant->id)
                ->where('slug', $slug)
                ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
