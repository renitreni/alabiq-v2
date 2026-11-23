<?php

namespace Database\Seeders;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seed a full menu for the existing owner account.
 *
 * All records are deterministic (keyed by restaurant + slug), so
 * re-running the seeder updates existing records instead of
 * duplicating them. Depends on UserSeeder (owner@example.com +
 * linked restaurant) having run first.
 */
class MenuSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's menu.
     */
    public function run(): void
    {
        $owner = User::where('email', 'owner@example.com')->first();

        if (! $owner) {
            $this->command?->warn('Owner account (owner@example.com) not found; run UserSeeder first.');

            return;
        }

        $restaurant = $owner->restaurant;

        if (! $restaurant) {
            $this->command?->warn('No restaurant linked to the owner account.');

            return;
        }

        foreach ($this->categories() as $categoryIndex => $category) {
            $menuCategory = MenuCategory::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'image' => null,
                    'sort_order' => $categoryIndex + 1,
                    'is_active' => true,
                ]
            );

            foreach ($category['items'] as $itemIndex => $item) {
                MenuItem::updateOrCreate(
                    ['restaurant_id' => $restaurant->id, 'slug' => $item['slug']],
                    array_merge($item, [
                        'menu_category_id' => $menuCategory->id,
                    ])
                )->update(['sort_order' => $itemIndex + 1]);
            }
        }

        $this->command?->info(
            "Menu seeded for {$restaurant->name}: ".
            count($this->categories()).' categories, '.
            array_sum(array_map(fn ($category) => count($category['items']), $this->categories())).' items.'
        );
    }

    /**
     * The full menu: categories with their items.
     *
     * @return array<int, array{name: string, slug: string, description: string, items: list<array}>>
     */
    private function categories(): array
    {
        return [
            [
                'name' => 'Burgers',
                'slug' => 'burgers',
                'description' => 'Handcrafted burgers served with toasted brioche buns.',
                'items' => $this->burgers(),
            ],
            [
                'name' => 'Pizza',
                'slug' => 'pizza',
                'description' => 'Stone-baked pizzas with house-made tomato sauce.',
                'items' => $this->pizzas(),
            ],
            [
                'name' => 'Pasta',
                'slug' => 'pasta',
                'description' => 'Classic pasta dishes cooked al dente.',
                'items' => $this->pastas(),
            ],
            [
                'name' => 'Salads',
                'slug' => 'salads',
                'description' => 'Fresh salads made with seasonal ingredients.',
                'items' => $this->salads(),
            ],
            [
                'name' => 'Desserts',
                'slug' => 'desserts',
                'description' => 'Sweet treats to finish your meal.',
                'items' => $this->desserts(),
            ],
            [
                'name' => 'Drinks',
                'slug' => 'drinks',
                'description' => 'Cold and hot beverages.',
                'items' => $this->drinks(),
            ],
        ];
    }

    /**
     * @return list<array>
     */
    private function burgers(): array
    {
        return [
            [
                'name' => 'Classic Cheeseburger',
                'slug' => 'classic-cheeseburger',
                'description' => 'Beef patty, American cheese, lettuce, tomato, and special sauce.',
                'price' => 8.99,
                'calories' => 650,
                'preparation_time' => '12 mins',
                'is_vegetarian' => false,
                'is_vegan' => false,
                'is_gluten_free' => false,
                'is_spicy' => false,
                'is_popular' => true,
                'metadata' => ['allergens' => ['gluten', 'dairy', 'sesame'], 'tags' => ['bestseller']],
            ],
            [
                'name' => 'Double Bacon Burger',
                'slug' => 'double-bacon-burger',
                'description' => 'Two beef patties, crispy bacon, cheddar, and caramelized onions.',
                'price' => 12.99,
                'compare_at_price' => 14.99,
                'calories' => 920,
                'preparation_time' => '15 mins',
                'is_vegetarian' => false,
                'is_vegan' => false,
                'is_gluten_free' => false,
                'is_spicy' => false,
                'is_popular' => true,
                'metadata' => ['allergens' => ['gluten', 'dairy', 'sesame'], 'tags' => []],
            ],
            [
                'name' => 'Crispy Chicken Burger',
                'slug' => 'crispy-chicken-burger',
                'description' => 'Buttermilk fried chicken, slaw, and garlic mayo.',
                'price' => 9.99,
                'calories' => 720,
                'preparation_time' => '14 mins',
                'is_vegetarian' => false,
                'is_vegan' => false,
                'is_gluten_free' => false,
                'is_spicy' => false,
                'is_popular' => false,
                'metadata' => ['allergens' => ['gluten', 'dairy', 'egg', 'sesame'], 'tags' => []],
            ],
            [
                'name' => 'Veggie Burger',
                'slug' => 'veggie-burger',
                'description' => 'House-made plant-based patty with avocado and lime aioli.',
                'price' => 9.49,
                'calories' => 540,
                'preparation_time' => '13 mins',
                'is_vegetarian' => true,
                'is_vegan' => true,
                'is_gluten_free' => false,
                'is_spicy' => false,
                'is_popular' => false,
                'metadata' => ['allergens' => ['gluten', 'sesame'], 'tags' => ['plant-based']],
            ],
            [
                'name' => 'BBQ Burger',
                'slug' => 'bbq-burger',
                'description' => 'Beef patty with smoky BBQ sauce, onion rings, and cheddar.',
                'price' => 11.49,
                'calories' => 850,
                'preparation_time' => '14 mins',
                'is_vegetarian' => false,
                'is_vegan' => false,
                'is_gluten_free' => false,
                'is_spicy' => false,
                'is_popular' => false,
                'metadata' => ['allergens' => ['gluten', 'dairy', 'sesame'], 'tags' => []],
            ],
        ];
    }

    /**
     * @return list<array>
     */
    private function pizzas(): array
    {
        return [
            [
                'name' => 'Margherita Pizza',
                'slug' => 'margherita-pizza',
                'description' => 'Tomato sauce, mozzarella, and fresh basil.',
                'price' => 10.99,
                'calories' => 580,
                'preparation_time' => '18 mins',
                'is_vegetarian' => true,
                'is_vegan' => false,
                'is_gluten_free' => false,
                'is_spicy' => false,
                'is_popular' => true,
                'metadata' => ['allergens' => ['gluten', 'dairy'], 'tags' => []],
            ],
            [
                'name' => 'Pepperoni Pizza',
                'slug' => 'pepperoni-pizza',
                'description' => 'Tomato sauce, mozzarella, and spicy pepperoni.',
                'price' => 11.99,
                'calories' => 700,
                'preparation_time' => '18 mins',
                'is_vegetarian' => false,
                'is_vegan' => false,
                'is_gluten_free' => false,
                'is_spicy' => true,
                'is_popular' => true,
                'metadata' => ['allergens' => ['gluten', 'dairy'], 'tags' => []],
            ],
            [
                'name' => 'BBQ Chicken Pizza',
                'slug' => 'bbq-chicken-pizza',
                'description' => 'Grilled chicken, red onion, mozzarella, and BBQ sauce.',
                'price' => 13.49,
                'calories' => 760,
                'preparation_time' => '20 mins',
                'is_vegetarian' => false,
                'is_vegan' => false,
                'is_gluten_free' => false,
                'is_spicy' => false,
                'is_popular' => false,
                'metadata' => ['allergens' => ['gluten', 'dairy'], 'tags' => []],
            ],
            [
                'name' => 'Veggie Pizza',
                'slug' => 'veggie-pizza',
                'description' => 'Mushrooms, bell peppers, olives, and mozzarella.',
                'price' => 12.49,
                'calories' => 640,
                'preparation_time' => '19 mins',
                'is_vegetarian' => true,
                'is_vegan' => false,
                'is_gluten_free' => false,
                'is_spicy' => false,
                'is_popular' => false,
                'metadata' => ['allergens' => ['gluten', 'dairy'], 'tags' => []],
            ],
            [
                'name' => 'Truffle Mushroom Pizza',
                'slug' => 'truffle-mushroom-pizza',
                'description' => 'Wild mushrooms, truffle oil, mozzarella, and parsley.',
                'price' => 14.99,
                'calories' => 690,
                'preparation_time' => '20 mins',
                'is_vegetarian' => true,
                'is_vegan' => false,
                'is_gluten_free' => false,
                'is_spicy' => false,
                'is_popular' => false,
                'metadata' => ['allergens' => ['gluten', 'dairy'], 'tags' => []],
            ],
        ];
    }

    /**
     * @return list<array>
     */
    private function pastas(): array
    {
        return [
            [
                'name' => 'Spaghetti Bolognese',
                'slug' => 'spaghetti-bolognese',
                'description' => 'Slow-cooked beef ragù over spaghetti.',
                'price' => 10.49,
                'calories' => 620,
                'preparation_time' => '20 mins',
                'is_vegetarian' => false,
                'is_vegan' => false,
                'is_gluten_free' => false,
                'is_spicy' => false,
                'is_popular' => false,
                'metadata' => ['allergens' => ['gluten'], 'tags' => []],
            ],
            [
                'name' => 'Creamy Carbonara',
                'slug' => 'creamy-carbonara',
                'description' => 'Pasta with pancetta, egg, pecorino, and black pepper.',
                'price' => 11.49,
                'calories' => 740,
                'preparation_time' => '18 mins',
                'is_vegetarian' => false,
                'is_vegan' => false,
                'is_gluten_free' => false,
                'is_spicy' => false,
                'is_popular' => true,
                'metadata' => ['allergens' => ['gluten', 'dairy', 'egg'], 'tags' => []],
            ],
            [
                'name' => 'Pesto Pasta',
                'slug' => 'pesto-pasta',
                'description' => 'Fusilli tossed in basil pesto with cherry tomatoes.',
                'price' => 10.99,
                'calories' => 560,
                'preparation_time' => '17 mins',
                'is_vegetarian' => true,
                'is_vegan' => false,
                'is_gluten_free' => false,
                'is_spicy' => false,
                'is_popular' => false,
                'metadata' => ['allergens' => ['gluten', 'dairy', 'nuts'], 'tags' => []],
            ],
            [
                'name' => 'Seafood Linguine',
                'slug' => 'seafood-linguine',
                'description' => 'Prawns, calamari, and mussels in a white-wine garlic sauce.',
                'price' => 14.99,
                'calories' => 680,
                'preparation_time' => '22 mins',
                'is_vegetarian' => false,
                'is_vegan' => false,
                'is_gluten_free' => false,
                'is_spicy' => false,
                'is_popular' => false,
                'metadata' => ['allergens' => ['gluten', 'shellfish'], 'tags' => []],
            ],
        ];
    }

    /**
     * @return list<array>
     */
    private function salads(): array
    {
        return [
            [
                'name' => 'Caesar Salad',
                'slug' => 'caesar-salad',
                'description' => 'Romaine, croutons, parmesan, and creamy Caesar dressing.',
                'price' => 7.49,
                'calories' => 320,
                'preparation_time' => '8 mins',
                'is_vegetarian' => true,
                'is_vegan' => false,
                'is_gluten_free' => false,
                'is_spicy' => false,
                'is_popular' => false,
                'metadata' => ['allergens' => ['gluten', 'dairy', 'egg'], 'tags' => []],
            ],
            [
                'name' => 'Greek Salad',
                'slug' => 'greek-salad',
                'description' => 'Cucumber, tomato, olives, and feta with oregano vinaigrette.',
                'price' => 6.99,
                'calories' => 260,
                'preparation_time' => '7 mins',
                'is_vegetarian' => true,
                'is_vegan' => false,
                'is_gluten_free' => true,
                'is_spicy' => false,
                'is_popular' => false,
                'metadata' => ['allergens' => ['dairy'], 'tags' => []],
            ],
            [
                'name' => 'Chicken Caesar Salad',
                'slug' => 'chicken-caesar-salad',
                'description' => 'Caesar salad topped with grilled chicken breast.',
                'price' => 9.49,
                'calories' => 480,
                'preparation_time' => '10 mins',
                'is_vegetarian' => false,
                'is_vegan' => false,
                'is_gluten_free' => false,
                'is_spicy' => false,
                'is_popular' => true,
                'metadata' => ['allergens' => ['gluten', 'dairy', 'egg'], 'tags' => []],
            ],
        ];
    }

    /**
     * @return list<array>
     */
    private function desserts(): array
    {
        return [
            [
                'name' => 'Chocolate Cake',
                'slug' => 'chocolate-cake',
                'description' => 'Rich layered chocolate cake with ganache.',
                'price' => 5.49,
                'calories' => 420,
                'preparation_time' => '5 mins',
                'is_vegetarian' => true,
                'is_vegan' => false,
                'is_gluten_free' => false,
                'is_spicy' => false,
                'is_popular' => true,
                'metadata' => ['allergens' => ['gluten', 'dairy', 'egg'], 'tags' => []],
            ],
            [
                'name' => 'New York Cheesecake',
                'slug' => 'new-york-cheesecake',
                'description' => 'Classic baked cheesecake with a berry compote.',
                'price' => 5.99,
                'calories' => 380,
                'preparation_time' => '5 mins',
                'is_vegetarian' => true,
                'is_vegan' => false,
                'is_gluten_free' => false,
                'is_spicy' => false,
                'is_popular' => false,
                'metadata' => ['allergens' => ['gluten', 'dairy', 'egg'], 'tags' => []],
            ],
            [
                'name' => 'Vanilla Ice Cream',
                'slug' => 'vanilla-ice-cream',
                'description' => 'Two scoops of premium vanilla ice cream.',
                'price' => 3.99,
                'calories' => 240,
                'preparation_time' => '3 mins',
                'is_vegetarian' => true,
                'is_vegan' => false,
                'is_gluten_free' => true,
                'is_spicy' => false,
                'is_popular' => false,
                'metadata' => ['allergens' => ['dairy'], 'tags' => []],
            ],
        ];
    }

    /**
     * @return list<array>
     */
    private function drinks(): array
    {
        return [
            [
                'name' => 'Coca Cola',
                'slug' => 'coca-cola',
                'description' => 'Chilled cola, 330 ml can.',
                'price' => 1.99,
                'calories' => 140,
                'preparation_time' => '2 mins',
                'is_vegetarian' => true,
                'is_vegan' => true,
                'is_gluten_free' => true,
                'is_spicy' => false,
                'is_popular' => true,
                'metadata' => ['allergens' => [], 'tags' => []],
            ],
            [
                'name' => 'Sprite',
                'slug' => 'sprite',
                'description' => 'Lemon-lime soda, 330 ml can.',
                'price' => 1.99,
                'calories' => 130,
                'preparation_time' => '2 mins',
                'is_vegetarian' => true,
                'is_vegan' => true,
                'is_gluten_free' => true,
                'is_spicy' => false,
                'is_popular' => false,
                'metadata' => ['allergens' => [], 'tags' => []],
            ],
            [
                'name' => 'Bottled Water',
                'slug' => 'bottled-water',
                'description' => 'Still mineral water, 500 ml bottle.',
                'price' => 0.99,
                'calories' => 0,
                'preparation_time' => '1 min',
                'is_vegetarian' => true,
                'is_vegan' => true,
                'is_gluten_free' => true,
                'is_spicy' => false,
                'is_popular' => false,
                'metadata' => ['allergens' => [], 'tags' => []],
            ],
            [
                'name' => 'Fresh Coffee',
                'slug' => 'fresh-coffee',
                'description' => 'Freshly brewed hot coffee.',
                'price' => 2.49,
                'calories' => 5,
                'preparation_time' => '4 mins',
                'is_vegetarian' => true,
                'is_vegan' => true,
                'is_gluten_free' => true,
                'is_spicy' => false,
                'is_popular' => false,
                'metadata' => ['allergens' => [], 'tags' => []],
            ],
            [
                'name' => 'Iced Tea',
                'slug' => 'iced-tea',
                'description' => 'Sweetened iced tea with lemon.',
                'price' => 2.49,
                'calories' => 80,
                'preparation_time' => '3 mins',
                'is_vegetarian' => true,
                'is_vegan' => true,
                'is_gluten_free' => true,
                'is_spicy' => false,
                'is_popular' => false,
                'metadata' => ['allergens' => [], 'tags' => []],
            ],
        ];
    }
}
