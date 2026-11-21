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
        Schema::create('payment_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('gateway'); // e.g. tap_payments
            $table->string('gateway_event_id')->nullable(); // charge id from the gateway
            $table->string('signature')->nullable();
            $table->boolean('signature_valid')->default(false);
            $table->string('status')->nullable(); // mapped payment status, if parseable
            $table->boolean('processed')->default(false);
            $table->json('payload');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['gateway', 'gateway_event_id']);
            $table->index('processed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_webhooks');
    }
};
