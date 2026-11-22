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
        Schema::create('delivery_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->foreignId('rider_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('assigned'); // assigned, accepted, rejected, completed, cancelled
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->decimal('delivery_fee', 8, 2)->nullable();
            $table->string('rejected_reason')->nullable();
            $table->timestamp('offered_at')->useCurrent();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['rider_id', 'status']);
            $table->index(['order_id', 'status']);
            $table->unique(['order_id', 'rider_id', 'status'], 'delivery_assignment_unique_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_assignments');
    }
};
