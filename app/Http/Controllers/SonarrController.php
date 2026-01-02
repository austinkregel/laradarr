<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\SonarrServiceContract;
use App\Exceptions\Integration\ApiException;
use App\Jobs\FollowUpEpisodeSearchJob;
use App\Models\Episode;
use App\Models\EpisodeSearchRequest;
use App\Models\Season;
use App\Models\Show;
use App\Services\MagnetParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class SonarrController extends Controller
{
    public function __construct(private SonarrServiceContract $sonarrService)
    {
    }

    public function pushRelease(Request $request): Response
    {
        $data = $request->validate([
            'title' => 'required|string',
            'magnetUrl' => 'nullable|string',
            'downloadUrl' => 'nullable|string',
            'publishDate' => 'nullable|date',
        ]);

        if (empty($data['magnetUrl']) && empty($data['downloadUrl'])) {
            return response()->json([
                'message' => 'Either magnetUrl or downloadUrl must be provided',
            ], 422);
        }

        $payload = $this->sonarrService->pushRelease(
            $data['title'],
            $data['magnetUrl'] ?? null,
            $data['downloadUrl'] ?? null,
            isset($data['publishDate']) ? (string) $data['publishDate'] : null,
        );

        return response()->json($payload);
    }

    public function pushSeasonRelease(Request $request, Season $season): Response
    {
        $data = $request->validate([
            'title' => 'required|string',
            'magnetUrl' => 'required|string',
            'publishDate' => 'nullable|date',

        ]);

        // Load the show relationship to check sonarr_id
        $season->load('show');

        if ($season->show->sonarr_id === null) {
            return back()->withErrors([
                'message' => 'Show is not linked to Sonarr',
            ]);
        }

        if (empty($data['magnetUrl']) && empty($data['downloadUrl'])) {
            return back()->withErrors([
                'message' => 'Either magnetUrl or downloadUrl must be provided',
            ]);
        }

        if (empty($data['publishDate'])) {
            $data['publishDate'] = now()->subDays(1)->toIso8601String();
        }

        // Title is required and should match the actual release title from the torrent
        $title = $data['title'];

        try {
            // Parse magnet URL to extract additional metadata
            $extra = [];
            
            // Include seriesId in the request for season packs
            if ($season->show->sonarr_id !== null) {
                $extra['seriesId'] = $season->show->sonarr_id;
            }

            // Extract additional information from magnet URL if provided
            if (!empty($data['magnetUrl'])) {
                $magnetData = MagnetParser::parse($data['magnetUrl']);
                
                // Include file size if available (helps Sonarr make better decisions)
                if ($magnetData['size'] !== null && $magnetData['size'] > 0) {
                    $extra['size'] = $magnetData['size'];
                }

                // Log extracted metadata for debugging
                Log::debug('sonarr.push_season_release.magnet_parsed', [
                    'season_id' => $season->id,
                    'extracted_title' => $magnetData['title'],
                    'extracted_size' => $magnetData['size'],
                    'extracted_info_hash' => $magnetData['infoHash'],
                    'tracker_count' => count($magnetData['trackers']),
                ]);
            }

            $payload = $this->sonarrService->pushRelease(
                $title,
                $data['magnetUrl'] ?? null,
                $data['downloadUrl'] ?? null,
                isset($data['publishDate']) ? (string) $data['publishDate'] : null,
                $extra,
            );


            info('Sonarr release pushed', $payload);
            // Check if Sonarr rejected the release (even though the API call succeeded)
            if (isset($payload['rejected']) && $payload['rejected'] === true) {
                $rejections = $payload['rejections'] ?? [];
                $rejectionMessage = !empty($rejections) 
                    ? 'Release was rejected by Sonarr: ' . implode('; ', $rejections)
                    : 'Release was rejected by Sonarr (check quality profile settings)';

                Log::warning('sonarr.push_season_release.rejected', [
                    'season_id' => $season->id,
                    'show_id' => $season->show->id,
                    'sonarr_id' => $season->show->sonarr_id,
                    'title' => $title,
                    'rejections' => $rejections,
                    'payload' => $payload,
                ]);

                return back()->withErrors([
                    'message' => $rejectionMessage,
                ]);
            }

            // Release was accepted
            return back()->with('success', 'Season release submitted to Sonarr successfully');
        } catch (ApiException $e) {
            // Extract error message from Sonarr's response
            $errorMessage = 'Unable to submit release to Sonarr';
            if ($e->responseBody !== null) {
                $responseData = json_decode($e->responseBody, true);
                if (is_array($responseData)) {
                    // Sonarr v3 API error format
                    if (isset($responseData['message'])) {
                        $errorMessage = $responseData['message'];
                    } elseif (isset($responseData['errorMessage'])) {
                        $errorMessage = $responseData['errorMessage'];
                    } elseif (isset($responseData[0]['errorMessage'])) {
                        $errorMessage = $responseData[0]['errorMessage'];
                    }
                } else {
                    // Try to extract message from plain text response
                    $errorMessage = $e->responseBody;
                }
            }

            Log::error('sonarr.push_season_release.failed', [
                'season_id' => $season->id,
                'show_id' => $season->show->id,
                'sonarr_id' => $season->show->sonarr_id,
                'title' => $title,
                'status' => $e->status,
                'response_body' => $e->responseBody,
                'error_message' => $errorMessage,
            ]);

            return back()->withErrors([
                'message' => $errorMessage,
            ]);
        }
    }

    public function searchEpisodes(Request $request): Response
    {
        $data = $request->validate([
            'episodeIds' => 'nullable|array',
            'episodeIds.*' => 'integer',
            'showId' => 'nullable|integer',
        ]);

        $user = $request->user();
        $episodeIds = $data['episodeIds'] ?? [];

        if (empty($episodeIds) && isset($data['showId'])) {
            $show = Show::findOrFail($data['showId']);

            if ($show->sonarr_id === null) {
                return response()->json([
                    'message' => 'Show is not linked to Sonarr',
                ], 422);
            }

            $episodeIds = $this->sonarrService->getMissingEpisodes($show->sonarr_id)
                ->map(fn ($episode) => $episode->id)
                ->values()
                ->all();
        }

        if (empty($episodeIds)) {
            return response()->json([
                'message' => 'No episode IDs provided',
            ], 422);
        }

        $payload = $this->sonarrService->searchEpisodes($episodeIds);
        $commandId = $payload['id'] ?? null;

        if ($commandId === null) {
            Log::warning('sonarr.search_episodes.no_command_id', [
                'episode_ids' => $episodeIds,
                'payload' => $payload,
            ]);
        } else {
            // Track search requests for individual episodes
            foreach ($episodeIds as $sonarrEpisodeId) {
                // Find local episode by sonarr_episode_id
                $localEpisode = Episode::where('sonarr_episode_id', $sonarrEpisodeId)->first();

                if ($localEpisode && $user) {
                    $searchRequest = EpisodeSearchRequest::create([
                        'user_id' => $user->id,
                        'episode_id' => $localEpisode->id,
                        'sonarr_episode_id' => $sonarrEpisodeId,
                        'sonarr_command_id' => $commandId,
                        'status' => 'pending',
                    ]);

                    // Dispatch follow-up job to check status after a delay
                    FollowUpEpisodeSearchJob::dispatch($searchRequest->id)->delay(now()->addSeconds(10));
                }
            }
        }

        return response()->json($payload);
    }

    public function searchSeason(Request $request, Season $season): Response
    {
        $user = $request->user();

        // Load the show relationship to check sonarr_id
        $season->load('show');

        if ($season->show->sonarr_id === null) {
            return response()->json([
                'message' => 'Show is not linked to Sonarr',
            ], 422);
        }

        // Get all episodes for this season that have Sonarr episode IDs
        $episodes = $season->episodes()
            ->whereNotNull('sonarr_episode_id')
            ->get();

        if ($episodes->isEmpty()) {
            return response()->json([
                'message' => 'No episodes with Sonarr IDs found for this season',
            ], 422);
        }

        $episodeIds = $episodes->pluck('sonarr_episode_id')->values()->all();

        $payload = $this->sonarrService->searchEpisodes($episodeIds);
        $commandId = $payload['id'] ?? null;

        if ($commandId === null) {
            Log::warning('sonarr.search_season.no_command_id', [
                'season_id' => $season->id,
                'episode_ids' => $episodeIds,
                'payload' => $payload,
            ]);
        } else {
            // Track search requests for all episodes in the season
            foreach ($episodes as $episode) {
                if ($episode->sonarr_episode_id && $user) {
                    $searchRequest = EpisodeSearchRequest::create([
                        'user_id' => $user->id,
                        'episode_id' => $episode->id,
                        'sonarr_episode_id' => $episode->sonarr_episode_id,
                        'sonarr_command_id' => $commandId,
                        'status' => 'pending',
                    ]);

                    // Dispatch follow-up job to check status after a delay
                    FollowUpEpisodeSearchJob::dispatch($searchRequest->id)->delay(now()->addSeconds(10));
                }
            }
        }

        return response()->json([
            'message' => 'Season search initiated',
            'episodes_count' => count($episodeIds),
            'command_id' => $commandId,
            ...$payload,
        ]);
    }

    public function getCommandStatus(int $commandId): Response
    {
        return response()->json($this->sonarrService->getCommandStatus($commandId));
    }

    public function getEpisodeSearchStatus(Request $request, int $episodeId): Response
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $searchRequest = EpisodeSearchRequest::where('episode_id', $episodeId)
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        if (!$searchRequest) {
            return response()->json(['message' => 'No search request found'], 404);
        }

        return response()->json([
            'id' => $searchRequest->id,
            'episode_id' => $searchRequest->episode_id,
            'status' => $searchRequest->status,
            'result' => $searchRequest->result,
            'result_message' => $searchRequest->result_message,
            'completed_at' => $searchRequest->completed_at?->toIso8601String(),
            'created_at' => $searchRequest->created_at->toIso8601String(),
        ]);
    }
}

