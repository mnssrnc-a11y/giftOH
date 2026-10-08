<?php

namespace App\Console\Commands;

use App\Services\FundingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Moves files attached to funding requests from the public disk (reachable by anyone with the
 * link) to the private disk, where FundingFileController serves them only to the requester and staff.
 * Paths stay the same, so no Firebase records change.
 */
class SecureFundingFiles extends Command
{
    protected $signature = 'funding:secure-files
        {--dry-run : List the files without moving them}
        {--orphans : Also move files left in the request upload folders that no request references}';

    /** Public folders that only ever held funding request uploads. */
    private const REQUEST_FOLDERS = ['doc_images', 'valid_ids', 'financial_reports', 'barangay_clearances', 'fund_documents', 'liquidations', 'disbursements'];

    protected $description = 'Move funding request files from public storage to private storage';

    public function handle(FundingService $funding): int
    {
        $public = Storage::disk('public');
        $private = Storage::disk(config('funding.files_disk', 'local'));
        $moved = 0;
        $missing = 0;

        $paths = [];
        foreach ($funding->getAllRequests() as $request) {
            foreach ($funding->filesOf($request) as $path) {
                if ($public->exists($path)) {
                    $paths[] = $path;
                } elseif (! $private->exists($path)) {
                    $missing++;
                }
            }
        }
        if ($this->option('orphans')) {
            foreach (self::REQUEST_FOLDERS as $folder) {
                $paths = array_merge($paths, $public->allFiles($folder));
            }
        }

        foreach (array_unique($paths) as $path) {
            $this->line(($this->option('dry-run') ? 'would move ' : 'moving ') . $path);
            if ($this->option('dry-run')) {
                continue;
            }

            // Copy, confirm, then remove the public copy.
            $private->put($path, $public->get($path));
            if ($private->exists($path) && $private->size($path) === $public->size($path)) {
                $public->delete($path);
                $moved++;
            } else {
                $this->error("Could not verify the private copy of {$path}; the public file was kept.");
            }
        }

        $this->info("{$moved} file(s) moved to private storage." . ($missing ? " {$missing} recorded file(s) were not found on either disk." : ''));

        return self::SUCCESS;
    }
}
