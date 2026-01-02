<?php
declare(strict_types=1);

namespace App\Contracts;

use App\Models\Movie;
use App\Models\Show;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

interface MediaTaggingServiceContract
{
    public function tagMediaFile(SpatieMedia $media, Show|Movie|null $model = null): void;

    public function extractAudioTracks(SpatieMedia $media): array;

    public function extractSubtitleTracks(SpatieMedia $media): array;

    /** @return array{has_dub: bool, has_sub: bool, primary_audio_language: string|null, dub_languages: string[], sub_languages: string[]} */
    public function detectAudioType(?Show $show, ?Movie $movie, array $audioTracks, array $subtitleTracks): array;
}

