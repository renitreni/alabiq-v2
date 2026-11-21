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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->string('name'); // Snapshot of menu item name
            $table->decimal('price', 8, 2); // Snapshot of base price
            $table->decimal('options_price', 8, 2)->default(0); // Snapshot of options total
            $table->decimal('final_price', 8, 2); // Snapshot of final price (price + options_price)
            $table->unsignedInteger('quantity');
            $table->json('options_snapshot')->nullable(); // Snapshot of selected options with names/prices
            $table->decimal('subtotal', 10, 2); // final_price * quantity
            $table->timestamps();

            $table->index(['order_id']);
            $table->index(['menu_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
