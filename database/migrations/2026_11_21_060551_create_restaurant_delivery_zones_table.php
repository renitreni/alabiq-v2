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
        Schema::create('restaurant_delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('zone_type')->default('polygon'); // polygon, circle, radius
            $table->json('coordinates'); // GeoJSON polygon or center+radius
            $table->decimal('delivery_fee', 8, 2)->default(0);
            $table->decimal('minimum_order', 8, 2)->default(0);
            $table->integer('estimated_delivery_time')->default(30);
            $table->integer('priority')->default(0); // higher = checked first
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['restaurant_branch_id', 'is_active']);
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurant_delivery_zones');
    }
};
