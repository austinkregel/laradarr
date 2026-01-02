<?php
declare(strict_types=1);

namespace App\Providers;

use App\Contracts\CredentialStoreContract;
use App\Contracts\LidarrServiceContract;
use App\Contracts\MagnetParserContract;
use App\Contracts\MediaTaggingServiceContract;
use App\Contracts\MetadataSyncServiceContract;
use App\Contracts\MovieClassificationServiceContract;
use App\Contracts\PlexServiceContract;
use App\Contracts\QBittorrentServiceContract;
use App\Contracts\RadarrServiceContract;
use App\Contracts\RecommendationServiceContract;
use App\Contracts\ShowClassificationServiceContract;
use App\Contracts\SonarrServiceContract;
use App\Contracts\TokenManagerContract;
use App\Contracts\TraktTvServiceContract;
use App\Contracts\TmdbServiceContract;
use App\Services\Auth\CredentialStore;
use App\Services\Auth\TokenManager;
use App\Services\LidarrService;
use App\Services\MagnetParser;
use App\Services\MediaTaggingService;
use App\Services\MetadataSyncService;
use App\Services\MovieClassificationService;
use App\Services\PlexService;
use App\Services\QBittorrentService;
use App\Services\RadarrService;
use App\Services\RecommendationService;
use App\Services\ShowClassificationService;
use App\Services\SonarrService;
use App\Services\TraktTvService;
use App\Services\TmdbService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Integration Services
        $this->app->singleton(SonarrServiceContract::class, SonarrService::class);
        $this->app->singleton(RadarrServiceContract::class, RadarrService::class);
        $this->app->singleton(LidarrServiceContract::class, LidarrService::class);
        $this->app->singleton(TraktTvServiceContract::class, TraktTvService::class);
        $this->app->singleton(QBittorrentServiceContract::class, QBittorrentService::class);

        // Standalone Services
        $this->app->singleton(PlexServiceContract::class, PlexService::class);
        $this->app->singleton(TmdbServiceContract::class, TmdbService::class);
        $this->app->singleton(RecommendationServiceContract::class, RecommendationService::class);
        $this->app->singleton(ShowClassificationServiceContract::class, ShowClassificationService::class);
        $this->app->singleton(MovieClassificationServiceContract::class, MovieClassificationService::class);
        $this->app->singleton(MediaTaggingServiceContract::class, MediaTaggingService::class);
        $this->app->singleton(MetadataSyncServiceContract::class, MetadataSyncService::class);

        // Utility Services
        $this->app->singleton(MagnetParserContract::class, MagnetParser::class);

        // Auth Services
        $this->app->singleton(CredentialStoreContract::class, CredentialStore::class);
        $this->app->singleton(TokenManagerContract::class, TokenManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
