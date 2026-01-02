<?php

namespace App\Jobs;

use App\Contracts\MetadataSyncServiceContract;
use App\Models\Show;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class SyncShowMetadataJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $showId,
    ) {
        $this->onQueue('metadata');
    }

    public function tags(): array
    {
        $show = Show::query()->select(['id', 'name', 'slug', 'trakt_id', 'tmdb_id'])->find($this->showId);

        return array_values(array_filter([
            'metadata',
            'metadata:show',
            $show?->id ? 'show_id:'.$show->id : null,
            $show?->tmdb_id ? 'tmdb_id:'.$show->tmdb_id : null,
            $show?->trakt_id ? 'trakt_id:'.$show->trakt_id : null,
            $show?->slug ? 'show_slug:'.$show->slug : null,
            $show?->name ? 'show:'.Str::limit($show->name, 50) : null,
        ]));
    }

    public function displayName(): string
    {
        return self::class.' (#'.$this->showId.')';
    }

    public function handle(MetadataSyncServiceContract $metadataSyncService): void
    {
        $show = Show::query()->find($this->showId);
        if (!$show) {
            return;
        }

        $metadataSyncService->syncShowMetadata($show);
    }
}



