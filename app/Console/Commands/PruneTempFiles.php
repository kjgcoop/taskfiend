<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * storage/app/temp holds scratch files only: zip extraction dirs and export
 * zips that are built, streamed, and normally deleted in the same request.
 * Anything left behind (a request that died mid-way, an error path that
 * returned early, or a test that never "sent" its download response) is
 * garbage. Stored templates live on the private disk, not here.
 */
class PruneTempFiles extends Command
{
    protected $signature = 'temp:prune {--hours=24 : Delete entries older than this many hours} {--dry-run : List what would be deleted without deleting}';
    protected $description = 'Delete stale files and directories from storage/app/temp';

    public function handle(): int
    {
        $dir = storage_path('app/temp');
        if (!is_dir($dir)) {
            $this->info('Nothing to prune.');
            return self::SUCCESS;
        }

        $cutoff = now()->subHours(max(1, (int) $this->option('hours')))->getTimestamp();
        $dryRun = $this->option('dry-run');
        $count  = 0;

        foreach (new \DirectoryIterator($dir) as $entry) {
            if ($entry->isDot() || $entry->getMTime() >= $cutoff) {
                continue;
            }

            $path = $entry->getPathname();
            $this->line(($dryRun ? 'Would delete: ' : 'Deleting: ') . $entry->getFilename());

            if (!$dryRun) {
                $entry->isDir() && !$entry->isLink() ? File::deleteDirectory($path) : File::delete($path);
            }
            $count++;
        }

        $this->info(($dryRun ? 'Would delete ' : 'Deleted ') . $count . ' ' . \Illuminate\Support\Str::plural('entry', $count) . '.');

        return self::SUCCESS;
    }
}
