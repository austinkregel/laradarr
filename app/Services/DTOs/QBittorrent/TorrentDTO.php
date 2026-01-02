<?php

declare(strict_types=1);

namespace App\Services\DTOs\QBittorrent;

readonly class TorrentDTO
{
    public function __construct(
        public string $hash,
        public string $name,
        public int $size,
        public float $progress,
        public int $dlspeed,
        public int $upspeed,
        public string $state,
        public int $eta,
        public int $added_on,
        public int $completion_on,
        public string $tracker,
        public int $dl_limit,
        public int $up_limit,
        public int $downloaded,
        public int $uploaded,
        public float $ratio,
        public int $seeding_time,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            hash: (string) $data['hash'],
            name: (string) $data['name'],
            size: (int) $data['size'],
            progress: (float) $data['progress'],
            dlspeed: (int) $data['dlspeed'],
            upspeed: (int) $data['upspeed'],
            state: (string) $data['state'],
            eta: (int) $data['eta'],
            added_on: (int) $data['added_on'],
            completion_on: (int) ($data['completion_on'] ?? 0),
            tracker: (string) ($data['tracker'] ?? ''),
            dl_limit: (int) ($data['dl_limit'] ?? -1),
            up_limit: (int) ($data['up_limit'] ?? -1),
            downloaded: (int) ($data['downloaded'] ?? 0),
            uploaded: (int) ($data['uploaded'] ?? 0),
            ratio: (float) ($data['ratio'] ?? 0.0),
            seeding_time: (int) ($data['seeding_time'] ?? 0),
        );
    }

    public function isStalled(int $stallThresholdSeconds = 3600): bool
    {
        // Consider stalled if:
        // 1. Downloading but no download speed for threshold time
        // 2. State is "stalledDL" or "metaDL" (downloading metadata) for too long
        if ($this->state === 'stalledDL' || $this->state === 'metaDL') {
            $timeSinceAdded = time() - $this->added_on;
            return $timeSinceAdded > $stallThresholdSeconds;
        }

        // If downloading but no speed and incomplete
        if (in_array($this->state, ['downloading', 'pausedDL'], true) && $this->progress < 1.0) {
            // Check if download speed is 0 and it's been a while
            if ($this->dlspeed === 0) {
                $timeSinceAdded = time() - $this->added_on;
                return $timeSinceAdded > $stallThresholdSeconds;
            }
        }

        return false;
    }

    public function isActive(): bool
    {
        return in_array($this->state, [
            'downloading',
            'uploading',
            'stalledUP',
            'stalledDL',
            'metaDL',
            'pausedDL',
            'pausedUP',
        ], true);
    }

    public function isCompleted(): bool
    {
        return $this->progress >= 1.0 || in_array($this->state, ['uploading', 'stalledUP', 'queuedUP', 'checkingUP', 'forcedUP'], true);
    }
}

