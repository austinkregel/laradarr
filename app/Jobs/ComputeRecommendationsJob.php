<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\RecommendationServiceContract;
use App\Models\Movie;
use App\Models\MovieRecommendation;
use App\Models\Show;
use App\Models\ShowRecommendation;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ComputeRecommendationsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const SHOW_LIMIT = 20;
    public const MOVIE_LIMIT = 20;

    public function __construct(public ?int $userId = null)
    {
    }

    public function handle(RecommendationServiceContract $service): void
    {
        $now = now();
        User::query()
            ->when($this->userId, fn ($query) => $query->where('id', $this->userId))
            ->chunk(25, function (EloquentCollection $users) use ($service, $now) {
                foreach ($users as $user) {
                    $this->computeForUser($service, $user, $now);
                }
            });
    }

    protected function computeForUser(RecommendationServiceContract $service, User $user, CarbonInterface $now): void
    {
        ShowRecommendation::query()->where('user_id', $user->id)->delete();
        MovieRecommendation::query()->where('user_id', $user->id)->delete();

        $this->storeShowRecommendations($user, $service->getShowRecommendations($user, self::SHOW_LIMIT), $now);
        $this->storeMovieRecommendations($user, $service->getMovieRecommendations($user, self::MOVIE_LIMIT), $now);
    }

    protected function storeShowRecommendations(User $user, Collection $recommendations, CarbonInterface $now): void
    {
        foreach ($recommendations as $entry) {
            $show = data_get($entry, 'show');

            if (!$show instanceof Show) {
                continue;
            }

            ShowRecommendation::create([
                'user_id' => $user->id,
                'show_id' => $show->id,
                'score' => (float) data_get($entry, 'score', 0.0),
                'computed_at' => $now,
            ]);
        }
    }

    protected function storeMovieRecommendations(User $user, Collection $recommendations, CarbonInterface $now): void
    {
        foreach ($recommendations as $entry) {
            $movie = data_get($entry, 'movie');

            if (!$movie instanceof Movie) {
                continue;
            }

            MovieRecommendation::create([
                'user_id' => $user->id,
                'movie_id' => $movie->id,
                'score' => (float) data_get($entry, 'score', 0.0),
                'computed_at' => $now,
            ]);
        }
    }
}

