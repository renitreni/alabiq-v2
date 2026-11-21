<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->decimal('price', 8, 2);
            $table->decimal('compare_at_price', 8, 2)->nullable(); // for discounts
            $table->integer('calories')->nullable();
            $table->string('preparation_time')->nullable(); // e.g., "15 mins"
            $table->boolean('is_vegetarian')->default(false);
            $table->boolean('is_vegan')->default(false);
            $table->boolean('is_gluten_free')->default(false);
            $table->boolean('is_spicy')->default(false);
            $table->boolean('is_popular')->default(false);
            $table->boolean('is_available')->default(true);
            $table->integer('sort_order')->default(0);
            $table->json('metadata')->nullable(); // allergens, nutritional info, tags
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['restaurant_id', 'slug']);
            $table->index(['restaurant_id', 'menu_category_id', 'is_available', 'sort_order']);
            $table->index(['restaurant_id', 'is_popular', 'is_available']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
