<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SonarrService
{
    public function getShows(): array
    {
        return Http::withHeaders([])
            ->get(config('services.sonarr.url').'/api/v3/series?apikey='.config('services.sonarr.api_key'))
            ->json();
    }

    public function getShow(int $show): array
    {
        return cache()->remember(
            'sonarr-show-'.$show,
            now()->addHour(),

            fn () =>
                Http::withHeaders([])
                ->get(config('services.sonarr.url').'/api/v3/series/'.$show.'?apikey='.config('services.sonarr.api_key'))
                ->json()
        );
    }

    public function getEpisodes(int $showId): array
    {
        return Http::withHeaders([])
            ->get(config('services.sonarr.url').'/api/v3/episode?seriesId='.$showId.'&apikey='.config('services.sonarr.api_key'))
            ->json();
    }

    public function getEpisodeFile(int $episodeFileId, int $showId): array
    {
        return Http::withHeaders([])
            ->get(config('services.sonarr.url').'/api/v3/episodefile/'.$episodeFileId.'?seriesId='.$showId.'&apikey='.config('services.sonarr.api_key'))
            ->json();
    }
}
