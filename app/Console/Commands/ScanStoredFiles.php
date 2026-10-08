<?php

namespace App\Console\Commands;

use App\Services\AuditLogger;
use App\Services\Security\MalwareScanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Local\LocalFilesystemAdapter;

/**
 * Re-scans every stored upload with today's antivirus signatures. Run daily by the scheduler;
 * with --quarantine, infected files are moved out of reach (storage/app/quarantine).
 */
class ScanStoredFiles extends Command
{
    protected $signature = 'security:scan-files {--quarantine : Move infected files to the quarantine folder}';

    protected $description = 'Scan stored uploads (request documents, receipts, profile photos) for malware';

    public function handle(MalwareScanner $scanner, AuditLogger $audit): int
    {
        $scanned = 0;
        $infected = [];

        foreach (config('security.uploads.stored', []) as $disk => $folders) {
            $storage = Storage::disk($disk);
            // Files kept outside this machine's drive (FILES_DRIVER=firebase) are scanned from a temp copy.
            $onDrive = $storage->getAdapter() instanceof LocalFilesystemAdapter;

            foreach ($folders as $folder) {
                foreach ($storage->allFiles($folder) as $path) {
                    $scanned++;
                    $local = $onDrive ? $storage->path($path) : $this->tempCopy($storage->get($path), $path);

                    try {
                        $result = $scanner->scan($local, basename($path));
                        if ($result['clean']) {
                            continue;
                        }

                        $infected[] = [$disk, $path, $result['threat']];
                        $this->warn("INFECTED {$disk}:{$path} · {$result['threat']}");
                        Log::warning('Stored file failed the malware scan', ['disk' => $disk, 'path' => $path, 'threat' => $result['threat']]);

                        if ($this->option('quarantine') && $storage->exists($path)) {
                            $target = rtrim(config('security.uploads.quarantine_path'), '/\\') . DIRECTORY_SEPARATOR . now()->format('Ymd') . DIRECTORY_SEPARATOR . $disk . DIRECTORY_SEPARATOR . $path;
                            File::ensureDirectoryExists(dirname($target));
                            if ($onDrive) {
                                File::move($local, $target);
                            } else {
                                File::copy($local, $target);
                                $storage->delete($path);
                            }
                            $this->line("  moved to quarantine: {$target}");
                        }
                        $audit->record('security', "Stored file flagged: {$path} · {$result['threat']}" . ($this->option('quarantine') ? ' (quarantined)' : ''), null, ['disk' => $disk]);
                    } finally {
                        if (! $onDrive && is_file($local)) {
                            @unlink($local);
                        }
                    }
                }
            }
        }

        $this->info("Scanned {$scanned} stored file(s); " . count($infected) . ' infected.');

        return count($infected) > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Writes a stored file to a temp file (keeping its extension, which the scanner checks).
     */
    private function tempCopy(?string $contents, string $path): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $temp = tempnam(sys_get_temp_dir(), 'scan_');
        $target = $extension !== '' ? $temp . '.' . $extension : $temp;
        if ($target !== $temp) {
            rename($temp, $target);
        }
        file_put_contents($target, (string) $contents);

        return $target;
    }
}
