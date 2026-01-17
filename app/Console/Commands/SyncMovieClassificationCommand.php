<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\MovieClassificationServiceContract;
use App\Models\Movie;
use Illuminate\Console\Command;

class SyncMovieClassificationCommand extends Command
{
    protected $signature = 'movies:classify {--movie_id=}';

    protected $description = 'Infer and attach categories/content warnings for movies using keyword rules and metadata';

    public function handle(MovieClassificationServiceContract $service): int
    {
        $movieId = $this->option('movie_id');

        if ($movieId !== null) {
            $movie = Movie::query()->find((int) $movieId);
            if (!$movie) {
                $this->error('Movie not found for movie_id='.(int) $movieId);
                return self::FAILURE;
            }
            $service->classifyAndAttach($movie);
            $this->info('Classified movie_id='.(int) $movieId);
            return self::SUCCESS;
        }

        $count = 0;
        Movie::query()
            ->select(['id'])
            ->orderBy('id')
            ->chunkById(250, function ($movies) use (&$count, $service) {
                foreach ($movies as $movie) {
                    $full = Movie::query()->find((int) $movie->id);
                    if (!$full) {
                        continue;
                    }
                    $service->classifyAndAttach($full);
                    $count++;
                }
            });

        $this->info("Classified {$count} movies");
        return self::SUCCESS;
    }
}





