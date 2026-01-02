<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\SonarrServiceContract;
use App\Events\EpisodeSearchStatusUpdated;
use App\Models\Episode;
use App\Models\EpisodeSearchRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class FollowUpEpisodeSearchJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $searchRequestId,
    ) {
        //
    }

    public function tags(): array
    {
        return ['sonarr', 'sonarr:search-followup', 'search-request:'.$this->searchRequestId];
    }

    public function displayName(): string
    {
        return self::class.' (Request #'.$this->searchRequestId.')';
    }

    public function handle(SonarrServiceContract $sonarrService): void
    {
        $searchRequest = EpisodeSearchRequest::find($this->searchRequestId);

        if (!$searchRequest) {
            Log::warning('sonarr.followup.search_request_not_found', [
                'search_request_id' => $this->searchRequestId,
            ]);
            return;
        }

        // Check command status
        try {
            $commandStatus = $sonarrService->getCommandStatus($searchRequest->sonarr_command_id);
            $status = $commandStatus['status'] ?? 'unknown';

            $searchRequest->status = $status;

            // If command is still in progress, reschedule for later
            if (in_array($status, ['queued', 'started'], true)) {
                $searchRequest->save();
                // Broadcast status update
                broadcast(new EpisodeSearchStatusUpdated($searchRequest));
                // Reschedule this job to check again in 30 seconds
                self::dispatch($this->searchRequestId)->delay(now()->addSeconds(30));
                return;
            }

            // Command completed or failed - determine the result
            if ($status === 'completed') {
                $result = $this->determineResult($sonarrService, $searchRequest);
                $searchRequest->result = $result['result'];
                $searchRequest->result_message = $result['message'];
                $searchRequest->completed_at = now();
            } elseif (in_array($status, ['failed', 'aborted', 'cancelled'], true)) {
                $searchRequest->result = 'error';
                $searchRequest->result_message = "Search command {$status}";
                $searchRequest->completed_at = now();
            }

            $searchRequest->save();
            // Broadcast final status update
            $event = new EpisodeSearchStatusUpdated($searchRequest);
            Log::info('sonarr.followup.broadcasting', [
                'search_request_id' => $searchRequest->id,
                'episode_id' => $searchRequest->episode_id,
                'status' => $searchRequest->status,
                'result' => $searchRequest->result,
                'broadcast_data' => $event->broadcastWith(),
            ]);
            broadcast($event);
        } catch (\Exception $e) {
            Log::error('sonarr.followup.error', [
                'search_request_id' => $this->searchRequestId,
                'error' => $e->getMessage(),
            ]);
            // Reschedule to retry
            self::dispatch($this->searchRequestId)->delay(now()->addMinutes(1));
        }
    }

    /**
     * Determine the result of the search by checking queue and history.
     *
     * @return array{result: string, message: string}
     */
    private function determineResult(SonarrServiceContract $sonarrService, EpisodeSearchRequest $searchRequest): array
    {
        $episode = $searchRequest->episode;
        $sonarrEpisodeId = $searchRequest->sonarr_episode_id;

        // Check if episode already has a file (was already imported)
        if ($episode && $episode->hasMedia()) {
            return [
                'result' => 'already_imported',
                'message' => 'Episode already has media files',
            ];
        }

        // Check if episode now has a file (was just imported)
        $episode->refresh();
        if ($episode->hasMedia()) {
            return [
                'result' => 'already_imported',
                'message' => 'Episode was imported',
            ];
        }

        // Check download queue for this episode
        try {
            $queue = $sonarrService->getQueue();
            // Queue can be an array directly or wrapped in a records key
            $queueItems = is_array($queue) && isset($queue['records']) ? $queue['records'] : (is_array($queue) ? $queue : []);

            foreach ($queueItems as $item) {
                // Queue items can have episode as object with id, or episodes as array
                $episode = $item['episode'] ?? null;
                $episodes = $item['episodes'] ?? null;

                if ($episode && isset($episode['id']) && (int) $episode['id'] === $sonarrEpisodeId) {
                    return [
                        'result' => 'added_to_queue',
                        'message' => 'Episode added to download queue',
                    ];
                }

                if (is_array($episodes)) {
                    foreach ($episodes as $ep) {
                        if (isset($ep['id']) && (int) $ep['id'] === $sonarrEpisodeId) {
                            return [
                                'result' => 'added_to_queue',
                                'message' => 'Episode added to download queue',
                            ];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('sonarr.followup.queue_check_failed', [
                'search_request_id' => $searchRequest->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Check history for recent activity
        try {
            $history = $sonarrService->getHistory($sonarrEpisodeId, page: 1, pageSize: 10);
            $historyRecords = is_array($history) ? ($history['records'] ?? $history) : [];

            // Check recent history entries (last 5 minutes)
            $recentCutoff = now()->subMinutes(5);
            foreach ($historyRecords as $record) {
                $date = $record['date'] ?? null;
                if ($date && strtotime($date) >= $recentCutoff->timestamp) {
                    $eventType = $record['eventType'] ?? '';
                    if (in_array($eventType, ['downloadFolderImported', 'episodeFileImported'], true)) {
                        return [
                            'result' => 'already_imported',
                            'message' => 'Episode was imported',
                        ];
                    }
                    if ($eventType === 'grabbed') {
                        return [
                            'result' => 'added_to_queue',
                            'message' => 'Episode was grabbed for download',
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('sonarr.followup.history_check_failed', [
                'search_request_id' => $searchRequest->id,
                'error' => $e->getMessage(),
            ]);
        }

        // If we get here, nothing was found
        return [
            'result' => 'nothing_found',
            'message' => 'No suitable release found',
        ];
    }
}
