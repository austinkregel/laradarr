<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\QBittorrentServiceContract;
use App\Models\Episode;
use App\Models\ManualImportFlag;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MonitorQBittorrentDownloadsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $maxExceptions = 1;
    public int $timeout = 120;

    public function __construct()
    {
        $this->onQueue('qbittorrent-monitor');
    }

    public function handle(QBittorrentServiceContract $qbittorrentService): void
    {
        $stallThresholdSeconds = (int) config('services.qbittorrent.stall_threshold_seconds', 3600);

        try {
            // Get all active/downloading torrents
            $downloadingTorrents = $qbittorrentService->getTorrents(['filter' => 'downloading']);

            $stalledCount = 0;
            $nudgedCount = 0;

            foreach ($downloadingTorrents as $torrent) {
                if (!$torrent->isStalled($stallThresholdSeconds)) {
                    continue;
                }

                $stalledCount++;

                Log::info('qbittorrent.torrent_stalled', [
                    'hash' => $torrent->hash,
                    'name' => $torrent->name,
                    'state' => $torrent->state,
                    'progress' => $torrent->progress,
                    'dlspeed' => $torrent->dlspeed,
                    'added_on' => $torrent->added_on,
                    'stall_duration_seconds' => time() - $torrent->added_on,
                ]);

                // Try to nudge the torrent
                $nudged = false;

                // First, try re-announcing (reconnect to trackers)
                if ($qbittorrentService->reannounce($torrent->hash)) {
                    Log::info('qbittorrent.torrent_reannounced', [
                        'hash' => $torrent->hash,
                        'name' => $torrent->name,
                    ]);
                    $nudged = true;
                }

                // If paused, try to resume
                if (in_array($torrent->state, ['pausedDL', 'pausedUP'], true)) {
                    if ($qbittorrentService->resume($torrent->hash)) {
                        Log::info('qbittorrent.torrent_resumed', [
                            'hash' => $torrent->hash,
                            'name' => $torrent->name,
                        ]);
                        $nudged = true;
                    }
                }

                // If still stalled and downloading, try force resume
                if ($torrent->state === 'stalledDL' && !$nudged) {
                    if ($qbittorrentService->forceResume($torrent->hash)) {
                        Log::info('qbittorrent.torrent_force_resumed', [
                            'hash' => $torrent->hash,
                            'name' => $torrent->name,
                        ]);
                        $nudged = true;
                    }
                }

                if ($nudged) {
                    $nudgedCount++;
                }
            }

            // Handle completed downloads (get all torrents and filter by completion)
            // Only check torrents that completed at least 5 minutes ago (give Sonarr time to import)
            $minCompletionTime = time() - 300; // 5 minutes ago
            $allTorrents = $qbittorrentService->getTorrents();
            $completedTorrents = $allTorrents->filter(function ($torrent) use ($minCompletionTime) {
                return $torrent->isCompleted() 
                    && $torrent->completion_on > 0 
                    && $torrent->completion_on < $minCompletionTime;
            });
            
            $deletedCount = 0;
            $flaggedCount = 0;

            foreach ($completedTorrents as $torrent) {
                // Get torrent properties to find content path
                $properties = $qbittorrentService->getTorrentProperties($torrent->hash);
                $contentPath = $properties['content_path'] ?? $properties['save_path'] ?? null;

                // Get torrent files
                $files = $qbittorrentService->getTorrentFiles($torrent->hash);
                $filePaths = array_map(fn ($file) => $file['name'] ?? '', $files);
                $filePaths = array_values(array_filter($filePaths));

                // Check if Sonarr has imported any of these files
                $imported = $this->checkIfSonarrHasFiles($contentPath, $filePaths);

                if ($imported) {
                    // Sonarr has the files, safe to delete
                    if ($qbittorrentService->deleteTorrent($torrent->hash, deleteFiles: false)) {
                        $deletedCount++;
                        Log::info('qbittorrent.torrent_deleted_after_import', [
                            'hash' => $torrent->hash,
                            'name' => $torrent->name,
                            'content_path' => $contentPath,
                        ]);

                        // Mark any existing manual import flag as resolved
                        ManualImportFlag::where('torrent_hash', $torrent->hash)
                            ->where('resolved', false)
                            ->update([
                                'resolved' => true,
                                'resolved_at' => now(),
                            ]);
                    }
                } else {
                    // Sonarr doesn't have the files, flag for manual import
                    // Only flag if not already flagged
                    if ($this->flagForManualImport($torrent, $contentPath, $filePaths)) {
                        $flaggedCount++;
                    }
                }
            }

            // Clean up manual import flags for torrents that no longer exist in qBittorrent
            $resolvedCount = $this->cleanupOrphanedManualImportFlags($qbittorrentService);

            if ($stalledCount > 0 || $deletedCount > 0 || $flaggedCount > 0 || $resolvedCount > 0) {
                Log::info('qbittorrent.monitor_complete', [
                    'downloading_torrents' => $downloadingTorrents->count(),
                    'stalled_count' => $stalledCount,
                    'nudged_count' => $nudgedCount,
                    'completed_torrents' => $completedTorrents->count(),
                    'deleted_count' => $deletedCount,
                    'flagged_count' => $flaggedCount,
                    'resolved_orphaned_flags' => $resolvedCount,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('qbittorrent.monitor_error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Check if Sonarr has imported any of the files from the torrent.
     */
    private function checkIfSonarrHasFiles(?string $contentPath, array $filePaths): bool
    {
        if (empty($filePaths) && $contentPath === null) {
            return false;
        }

        // Extract file names from paths
        $fileNames = array_map(fn ($path) => basename($path), $filePaths);
        $fileNames = array_values(array_filter($fileNames));

        // Check if any episode media has a path or name matching the torrent files
        $hasMatch = false;

        if (!empty($fileNames)) {
            // Check by file name
            $hasMatch = Episode::query()
                ->whereHas('media', function ($query) use ($fileNames) {
                    $query->whereIn('name', $fileNames);
                })
                ->exists();
        }

        // Also check by path if content path is available
        if (!$hasMatch && $contentPath !== null) {
            $hasMatch = Episode::query()
                ->whereHas('media', function ($query) use ($contentPath) {
                    // Check if media path is within the content path
                    $query->where('custom_properties->path', 'like', $contentPath . '%')
                        ->orWhereJsonContains('custom_properties->path', $contentPath);
                })
                ->exists();
        }

        return $hasMatch;
    }

    /**
     * Flag a torrent for manual import.
     *
     * @return bool True if flagged (new flag created), false if already flagged
     */
    private function flagForManualImport(
        \App\Services\DTOs\QBittorrent\TorrentDTO $torrent,
        ?string $contentPath,
        array $filePaths
    ): bool {
        // Check if already flagged
        $existing = ManualImportFlag::where('torrent_hash', $torrent->hash)
            ->where('resolved', false)
            ->first();

        if ($existing) {
            return false; // Already flagged
        }

        ManualImportFlag::create([
            'torrent_hash' => $torrent->hash,
            'torrent_name' => $torrent->name,
            'content_path' => $contentPath,
            'file_paths' => $filePaths,
            'reason' => 'Completed download not found in Sonarr - requires manual import',
        ]);

        Log::warning('qbittorrent.torrent_flagged_manual_import', [
            'hash' => $torrent->hash,
            'name' => $torrent->name,
            'content_path' => $contentPath,
            'file_count' => count($filePaths),
        ]);

        return true;
    }

    public function tags(): array
    {
        return ['qbittorrent', 'monitor'];
    }

    public function displayName(): string
    {
        return 'Monitor qBittorrent Downloads';
    }

    /**
     * Clean up manual import flags for torrents that no longer exist in qBittorrent.
     *
     * @return int Number of flags resolved
     */
    private function cleanupOrphanedManualImportFlags(QBittorrentServiceContract $qbittorrentService): int
    {
        $unresolvedFlags = ManualImportFlag::where('resolved', false)
            ->whereNotNull('torrent_hash')
            ->get();

        if ($unresolvedFlags->isEmpty()) {
            return 0;
        }

        // Get all torrent hashes from qBittorrent
        $allTorrents = $qbittorrentService->getTorrents();
        $existingHashes = $allTorrents->pluck('hash')->toArray();

        $resolvedCount = 0;

        foreach ($unresolvedFlags as $flag) {
            // If the torrent hash is not in qBittorrent, mark the flag as resolved
            if (!in_array($flag->torrent_hash, $existingHashes, true)) {
                $flag->update([
                    'resolved' => true,
                    'resolved_at' => now(),
                ]);

                Log::info('qbittorrent.manual_import_flag_resolved_orphaned', [
                    'flag_id' => $flag->id,
                    'torrent_hash' => $flag->torrent_hash,
                    'torrent_name' => $flag->torrent_name,
                    'reason' => 'Torrent no longer exists in qBittorrent',
                ]);

                $resolvedCount++;
            }
        }

        return $resolvedCount;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('qbittorrent.monitor_job_failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}

