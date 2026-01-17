<?php

namespace App\Jobs;

use App\Contracts\TraktTvServiceContract;
use App\Models\Episode;
use App\Models\Show;
use App\Models\User;
use App\Jobs\SyncShowMetadataJob;
use App\Services\DTOs\Trakt\EpisodeDTO as TraktEpisodeDTO;
use App\Services\DTOs\Trakt\ShowDTO as TraktShowDTO;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncTraktTvShowsAndProgressJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user,
    )
    {
        //
    }

    protected function findShow(TraktShowDTO $show)
    {
        $showIds = [
            'trakt_id' => $show->ids['trakt'] ?? $show->id,
            'imdb_id' => $show->ids['imdb'] ?? null,
            'tvdb_id' => $show->ids['tvdb'] ?? null,
            'tmdb_id' => $show->ids['tmdb'] ?? null,
            'slug' => $show->ids['slug'] ?? null,
        ];

        if (in_array((int) ($showIds['trakt_id'] ?? 0), [168837], true)) {
            return null;
        }


        foreach ($showIds as $columnName => $id) {
            $localShow = Show::query()->with('seasons.episodes')->firstWhere($columnName, $id);

            if (isset($localShow)) {
                return $localShow;
            }
        }

        $localShow = new Show();
        $localShow->name = $show->name;
        foreach ($showIds as $attribute => $value) {
            $localShow->{$attribute} = $value;
        }
        $localShow->release_year = $show->releaseYear;
        $localShow->save();

        return $localShow;
    }
    /**
     * Execute the job.
     */
    public function handle(TraktTvServiceContract $traktTvService): void
    {
        // Check if user has Trakt connected
        if (!$this->user->hasTraktConnected()) {
            return;
        }

        // Create a user-scoped service to use the user's own Trakt tokens
        $userTraktService = $traktTvService->forUser($this->user->id);
        $series = $userTraktService->findWatchedShows();

        /** @var TraktShowDTO $show */
        foreach ($series as $show) {
            $localShow = $this->findShow($show);
            $attributes = [
                'name' => $show->name,
                'trakt_id' => $show->ids['trakt'] ?? $show->id,
                'tvdb_id' => $show->ids['tvdb'] ?? null,
                'imdb_id' => $show->ids['imdb'] ?? null,
                'tmdb_id' => $show->ids['tmdb'] ?? null,
                'slug' => $show->ids['slug'] ?? null,
            ];

            if (empty($localShow)) {
                continue;
            }

            $localShow->last_watched_at = max(
                array_map(fn (TraktEpisodeDTO $episode) => Carbon::parse($episode->watchedAt, 'UTC'), $show->episodes)
            );

            foreach ($attributes as $attribute => $value) {
                if ($localShow->{$attribute} === $value) {
                    continue;
                }
                $localShow->{$attribute} = $value;
            }
            if ($localShow->isDirty()) {
                $localShow->save();
            }

            if (!empty($localShow->tmdb_id) || !empty($localShow->trakt_id)) {
                dispatch(new SyncShowMetadataJob((int) $localShow->id));
            }

            $watchedEpisodeCount = 0;
            // Now we need to match the episodes from the show, with the localShow.
            /** @var TraktEpisodeDTO $episode */
            foreach ($show->episodes as $episode) {
                $localSeason = $localShow->seasons()
                    ->firstOrCreate(['season' => $episode->season], [
                        'name' => 'Season ' . $episode->season,
                    ]);

                $localEpisode = $localSeason
                    ->episodes()
                    ->firstWhere('episode_number', $episode->number);

                if (empty($localEpisode)) {
                    continue;
                }

                $watchedEpisodeCount++;

                if ($this->user->watchedEpisodes()->where('episode_id', $localEpisode->id)->exists()) {
                    continue;
                }

                $this->user
                    ->watchedEpisodes()
                    ->attach(
                        $localEpisode->id,
                        [
                            'watched_at' => Carbon::parse($episode->watchedAt),
                            'season_id' => $localSeason->id
                        ]
                    );
            }

            if ($watchedEpisodeCount === $localShow->episodes()->count()) {
                if ($this->user->completedShows()->where('show_id', $localShow->id)->exists()) {
                    continue;
                }

                $this->user->completedShows()->attach($localShow->id, [
                    'completed_at' => Carbon::now(),
                ]);
            }
        }
    }
}
