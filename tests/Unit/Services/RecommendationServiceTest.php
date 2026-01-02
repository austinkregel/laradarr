<?php

namespace Tests\Unit\Services;

use App\Models\Category;
use App\Models\Show;
use App\Models\User;
use App\Services\RecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_show_recommendations_falls_back_for_new_user(): void
    {
        $mostPopular = Show::create([
            'name' => 'Most Popular Show',
            'release_year' => 2023,
            'poster_image' => 'https://example.com/most-popular.jpg',
            'seasons' => 1,
            'episodes' => 12,
            'type' => 'anime',
            'trakt_rating' => 9.5,
            'genres' => ['action'],
        ]);
        $this->addEpisodeWithEnglishDub($mostPopular, 1, 1);

        $secondary = Show::create([
            'name' => 'Secondary Show',
            'release_year' => 2022,
            'poster_image' => 'https://example.com/secondary.jpg',
            'seasons' => 1,
            'episodes' => 8,
            'type' => 'anime',
            'trakt_rating' => 8.7,
            'genres' => ['drama'],
        ]);
        $this->addEpisodeWithEnglishDub($secondary, 1, 1);

        $user = User::factory()->create();
        $service = app(RecommendationService::class);

        $recommendations = $service->getShowRecommendations($user, 2);

        $this->assertCount(2, $recommendations);
        $this->assertSame('Most Popular Show', $recommendations->first()['show']->name);
    }

    public function test_user_preferences_prioritizes_matching_genres(): void
    {
        $category = Category::create([
            'name' => 'Action',
            'slug' => 'action',
            'type' => 'genre',
        ]);

        $completed = Show::create([
            'name' => 'Completed Action',
            'release_year' => 2021,
            'poster_image' => 'https://example.com/completed.jpg',
            'seasons' => 1,
            'episodes' => 10,
            'type' => 'anime',
            'trakt_rating' => 9.0,
            'genres' => ['action'],
        ]);
        $completed->categories()->attach($category->id);
        $completed->attachTags(['Action']);
        $this->addEpisodeWithEnglishDub($completed, 1, 1);

        $target = Show::create([
            'name' => 'Target Action',
            'release_year' => 2024,
            'poster_image' => 'https://example.com/target.jpg',
            'seasons' => 1,
            'episodes' => 12,
            'type' => 'anime',
            'trakt_rating' => 8.8,
            'genres' => ['action'],
        ]);
        $target->categories()->attach($category->id);
        $target->attachTags(['Action']);
        $this->addEpisodeWithEnglishDub($target, 1, 1);

        $drama = Show::create([
            'name' => 'Neutral Drama',
            'release_year' => 2020,
            'poster_image' => 'https://example.com/drama.jpg',
            'seasons' => 1,
            'episodes' => 9,
            'type' => 'anime',
            'trakt_rating' => 8.5,
            'genres' => ['drama'],
        ]);
        $drama->attachTags(['Drama']);
        $this->addEpisodeWithEnglishDub($drama, 1, 1);

        $user = User::factory()->create();
        $user->completedShows()->attach($completed->id, ['completed_at' => now()]);

        $service = app(RecommendationService::class);
        $recommendations = $service->getShowRecommendations($user, 2);

        $this->assertSame('Target Action', $recommendations->first()['show']->name);
        $this->assertCount(2, $recommendations);
    }
}

