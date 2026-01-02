<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\EpisodeSearchRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class EpisodeSearchStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        private EpisodeSearchRequest $searchRequest,
    ) {
        //
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->searchRequest->user_id),
        ];
    }

    public function getSearchRequest(): EpisodeSearchRequest
    {
        return $this->searchRequest;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'episode-search-status-updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'episode_id' => $this->searchRequest->episode_id,
            'status' => $this->searchRequest->status,
            'result' => $this->searchRequest->result,
            'result_message' => $this->searchRequest->result_message,
            'completed_at' => $this->searchRequest->completed_at?->toIso8601String(),
        ];
    }
}
