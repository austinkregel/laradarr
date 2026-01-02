<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\RadarrServiceContract;
use App\Exceptions\Integration\ApiException;
use App\Models\Movie;
use App\Services\MagnetParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RadarrController extends Controller
{
    public function __construct(private RadarrServiceContract $radarrService)
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

        $payload = $this->radarrService->pushRelease(
            $data['title'],
            $data['magnetUrl'] ?? null,
            $data['downloadUrl'] ?? null,
            isset($data['publishDate']) ? (string) $data['publishDate'] : null,
        );

        return response()->json($payload);
    }

    public function searchMovie(Request $request, Movie $movie): Response
    {
        if ($movie->radarr_id === null) {
            return response()->json([
                'message' => 'Movie is not linked to Radarr',
            ], 422);
        }

        $payload = $this->radarrService->searchMovie([$movie->radarr_id]);
        $commandId = $payload['id'] ?? null;

        if ($commandId === null) {
            Log::warning('radarr.search_movie.no_command_id', [
                'movie_id' => $movie->id,
                'radarr_id' => $movie->radarr_id,
                'payload' => $payload,
            ]);
        }

        return response()->json([
            'message' => 'Movie search initiated',
            'command_id' => $commandId,
            ...$payload,
        ]);
    }

    public function getCommandStatus(int $commandId): Response
    {
        return response()->json($this->radarrService->getCommandStatus($commandId));
    }
}

