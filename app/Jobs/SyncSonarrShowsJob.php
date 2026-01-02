<?php

namespace App\Jobs;

use App\Contracts\SonarrServiceContract;
use App\Services\DTOs\Sonarr\ShowDTO;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncSonarrShowsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(SonarrServiceContract $sonarrService): void
    {
        $series = $sonarrService->getShows();

        /** @var ShowDTO $show */
        foreach ($series as $show) {
            dispatch(new SyncSonarrShowJob($this->showDtoToArray($show)));
        }
    }

    protected function showDtoToArray(ShowDTO $show): array
    {
        return [
            'id' => $show->id,
            'title' => $show->title,
            'titleSlug' => $show->titleSlug,
            'year' => $show->year,
            'overview' => $show->overview,
            'seriesType' => $show->seriesType,
            'path' => $show->path,
            'alternateTitles' => $show->alternateTitles,
            'images' => $show->images,
            'seasons' => $show->seasons,
            'imdbId' => $show->imdbId,
            'tvdbId' => $show->tvdbId,
            'tmdbId' => $show->tmdbId,
            'firstAired' => $show->firstAired?->toIso8601String(),
            'statistics' => $show->statistics,
        ];
    }
}
