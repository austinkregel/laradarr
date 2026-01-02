<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ScanDirectoryJob implements ShouldQueue
{
    use Queueable;


    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $directory,
        public string $disk = 'local',
    ) {
        static::onQueue('scan');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $directories = \Illuminate\Support\Facades\Storage::disk($this->disk)->directories($this->directory);

        foreach ($directories as $folder) {
            // Dispatch a new job for each subdirectory
            dispatch(new self($folder, $this->disk));
        }

        $files = \Illuminate\Support\Facades\Storage::disk($this->disk)->files($this->directory);

        foreach ($files as $file) {
            dispatch(new DetectFileMetadataJob($file, $this->disk));
        }
    }
}
