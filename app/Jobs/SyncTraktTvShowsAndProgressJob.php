<?php

namespace App\Jobs;

use App\Models\Episode;
use App\Models\Show;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

    protected function findShow($show)
    {
        $showIds = [
            'trakt_id' => $show['ids']['trakt'],
            'imdb_id' => $show['ids']['imdb'],
            'tvdb_id' => $show['ids']['tvdb'],
            'tmdb_id' => $show['ids']['tmdb'],
            'slug' => $show['ids']['slug'],
        ];

        if (in_array($showIds['trakt_id'], [168837])) {
            return null;
        }


        foreach ($showIds as $columnName => $id) {
            $localShow = Show::query()->with('seasons.episodes')->firstWhere($columnName, $id);

            if (isset($localShow)) {
                return $localShow;
            }
        }

        $localShow = new Show();
        $localShow->name = $show['name'];
        foreach ($showIds as $attribute => $value) {
            $localShow->{$attribute} = $value;
        }
        $localShow->release_year = $show['release_year'];
        $localShow->save();

        return $localShow;
    }
    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $series = app(\App\Services\TraktTvService::class)->findWatchedShows();

        foreach ($series as $show) {
            $localShow = $this->findShow($show);
            $attributes = [
                'name' => $show['name'],
                'trakt_id' => $show['ids']['trakt'],
                'tvdb_id' => $show['ids']['tvdb'],
                'imdb_id' => $show['ids']['imdb'],
                'tmdb_id' => $show['ids']['tmdb'],
                'slug' => $show['ids']['slug'],
            ];

            if (empty($localShow)) {
                continue;
            }

            $localShow->last_watched_at = max(
                array_map(fn ($episode) => Carbon::parse($episode['watched_at'], 'UTC'), $show['episodes'])
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

            $watchedEpisodeCount = 0;
            // Now we need to match the episodes from the show, with the localShow.
            foreach ($show['episodes'] as $episode) {
                $localSeason = $localShow->seasons()
                    ->firstOrCreate(['season' => $episode['season']], [
                        'name' => 'Season ' . $episode['season'],
                    ]);

                $localEpisode = $localSeason
                    ->episodes()
                    ->firstWhere('episode_number', $episode['number']);

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
                            'watched_at' => Carbon::parse($episode['watched_at']),
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
