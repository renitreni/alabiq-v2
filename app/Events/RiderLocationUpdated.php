<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast a rider's new position to everyone tracking the order.
 *
 * Implements ShouldBroadcast so any configured broadcast driver (log, redis,
 * pusher) picks it up; no specific driver is hard-required.
 */
class RiderLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $orderId,
        public int $riderId,
        public float $lat,
        public float $lng,
        public mixed $recordedAt,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("tracking.{$this->orderId}");
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->orderId,
            'rider_id' => $this->riderId,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'recorded_at' => $this->recordedAt instanceof \DateTimeInterface
                ? $this->recordedAt->toISOString()
                : $this->recordedAt,
        ];
    }
}
