<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DashboardUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $message,
        public array $data = [],
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('dashboard'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'dashboard.updated';
    }
}