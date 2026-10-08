<?php

namespace App\Console\Commands;

use App\Services\FirebaseService;
use App\Support\Storage\FirebaseDatabaseAdapter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use League\Flysystem\Config;

/**
 * Copies the uploads already on this computer (storage/app/private and storage/app/public) into
 * the Firebase file store, so the online deployment (FILES_DRIVER=firebase) can open documents that
 * were uploaded locally. Run it from the project folder on the PC that has the files:
 *
 *     php artisan files:push-to-firebase --dry-run
 *     php artisan files:push-to-firebase
 */
class PushFilesToFirebase extends Command
{
    protected $signature = 'files:push-to-firebase
        {--dry-run : List what would be copied without uploading}
        {--force : Re-upload files that are already in Firebase}';

    protected $description = 'Copy local uploads into the Firebase file store used by FILES_DRIVER=firebase';

    /** Same roots as config/filesystems.php when FILES_DRIVER=firebase. */
    private const TARGETS = [
        'app/private' => ['root' => 'file_store/local', 'visibility' => 'private'],
        'app/public' => ['root' => 'file_store/public', 'visibility' => 'public'],
    ];

    /** Folders under storage/app that are not uploads. */
    private const SKIP_PREFIXES = ['firebase/', 'quarantine/'];

    public function handle(FirebaseService $firebase): int
    {
        $copied = 0;
        $skipped = 0;
        $bytes = 0;

        foreach (self::TARGETS as $folder => $target) {
            $base = storage_path($folder);
            if (! is_dir($base)) {
                continue;
            }

            $adapter = new FirebaseDatabaseAdapter($firebase, $target['root'], $target['visibility']);

            foreach (File::allFiles($base) as $file) {
                $path = str_replace('\\', '/', $file->getRelativePathname());
                if ($file->getFilename() === '.gitignore' || $this->skipped($path)) {
                    continue;
                }

                if (! $this->option('force') && $adapter->fileExists($path) && $adapter->fileSize($path)->fileSize() === $file->getSize()) {
                    $skipped++;
                    continue;
                }

                $this->line(sprintf('%s %s/%s (%s KB)', $this->option('dry-run') ? 'would copy' : 'copying', $target['root'], $path, number_format($file->getSize() / 1024, 1)));
                if (! $this->option('dry-run')) {
                    $adapter->write($path, File::get($file->getPathname()), new Config(['visibility' => $target['visibility']]));
                }
                $copied++;
                $bytes += $file->getSize();
            }
        }

        $this->info(sprintf('%s %d file(s), %s MB; %d already in Firebase.', $this->option('dry-run') ? 'Would copy' : 'Copied', $copied, number_format($bytes / 1048576, 2), $skipped));

        return self::SUCCESS;
    }

    private function skipped(string $path): bool
    {
        foreach (self::SKIP_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
