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
        Schema::create('menu_item_availability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('restaurant_branch_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_available')->default(true);
            $table->decimal('price_override', 8, 2)->nullable();
            $table->time('available_from')->nullable();
            $table->time('available_until')->nullable();
            $table->json('days_of_week')->nullable(); // [1,2,3,4,5,6,7] for Mon-Sun, null = all days
            $table->date('available_from_date')->nullable();
            $table->date('available_until_date')->nullable();
            $table->integer('stock_quantity')->nullable(); // null = unlimited
            $table->integer('low_stock_threshold')->default(5);
            $table->boolean('track_stock')->default(false);
            $table->timestamps();

            $table->unique(['menu_item_id', 'restaurant_branch_id']);
            $table->index(['restaurant_branch_id', 'is_available']);
            $table->index(['menu_item_id', 'is_available']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_item_availability');
    }
};
