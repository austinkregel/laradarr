<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ComputeRecommendationsJob;
use App\Models\Category;
use App\Models\Movie;
use App\Models\MovieRecommendation;
use App\Models\Show;
use App\Models\ShowRecommendation;
use App\Models\User;
use App\Services\RecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComputeRecommendationsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_populates_show_and_movie_recommendations(): void
    {
        $category = Category::create([
            'name' => 'Action',
            'slug' => 'action',
            'type' => 'genre',
        ]);

        $onePiece = Show::create([
            'name' => 'One Piece',
            'release_year' => 1999,
            'poster_image' => 'https://example.com/one-piece.jpg',
            'seasons' => 1,
            'episodes' => 100,
            'type' => 'anime',
            'trakt_rating' => 9.7,
            'genres' => ['action', 'adventure'],
        ]);
        $onePiece->categories()->attach($category->id);
        $onePiece->attachTags(['Action']);
        $this->addEpisodeWithEnglishDub($onePiece, 1, 1);

        $naruto = Show::create([
            'name' => 'Naruto',
            'release_year' => 2002,
            'poster_image' => 'https://example.com/naruto.jpg',
            'seasons' => 1,
            'episodes' => 220,
            'type' => 'anime',
            'trakt_rating' => 8.8,
            'genres' => ['action'],
        ]);
        $naruto->categories()->attach($category->id);
        $naruto->attachTags(['Action']);
        $this->addEpisodeWithEnglishDub($naruto, 1, 1);

        $romance = Show::create([
            'name' => 'Pink Romance',
            'release_year' => 2020,
            'poster_image' => 'https://example.com/romance.jpg',
            'seasons' => 1,
            'episodes' => 12,
            'type' => 'anime',
            'trakt_rating' => 8.2,
            'genres' => ['romance'],
        ]);
        $this->addEpisodeWithEnglishDub($romance, 1, 1);

        $user = User::factory()->create();
        $user->completedShows()->attach($onePiece->id, ['completed_at' => now()]);

        $movie = Movie::create([
            'radarr_id' => 1,
            'name' => '18+ Thriller',
            'runtime' => 120,
            'movie_file_id' => 1,
            'is_available' => true,
            'added_at' => now(),
        ]);

        $job = new ComputeRecommendationsJob($user->id);
        $job->handle(app(RecommendationService::class));

        $this->assertDatabaseHas('show_recommendations', [
            'user_id' => $user->id,
            'show_id' => $naruto->id,
        ]);
        $this->assertDatabaseMissing('show_recommendations', [
            'user_id' => $user->id,
            'show_id' => $onePiece->id,
        ]);

        $topShow = ShowRecommendation::where('user_id', $user->id)
            ->orderByDesc('score')
            ->first();

        $this->assertNotNull($topShow);
        $this->assertSame('Naruto', $topShow->show->name);

        $this->assertDatabaseHas('movie_recommendations', [
            'user_id' => $user->id,
            'movie_id' => $movie->id,
        ]);
    }
}


