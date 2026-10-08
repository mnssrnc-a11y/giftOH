<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * Starts the AI price update ("php artisan prices:update") as its own background process.
 *
 * The update reads the DTI bulletin, searches the TGP store item by item and asks the AI
 * providers, which takes a few minutes. Run inside a web request it hit PHP's time limit
 * (60-120 s) and stopped half way, and on the single-threaded dev server it froze every other
 * page while it ran. A separate process has no time limit and leaves the site responsive.
 */
class PriceUpdateRunner
{
    public const LOCK_KEY = 'prices:update:running';

    public const STATUS_KEY = 'prices:update:status';

    /** A run normally takes 2-6 minutes; a crashed run frees the lock after this long. */
    private const LOCK_MINUTES = 30;

    public function isRunning(): bool
    {
        return Cache::has(self::LOCK_KEY);
    }

    /**
     * @return array{state: string, started_at?: string, finished_at?: string, message?: string, updated?: int}|null
     */
    public function status(): ?array
    {
        $status = Cache::get(self::STATUS_KEY);

        if (is_array($status) && $status['state'] === 'running' && ! $this->isRunning()) {
            // The process died without reporting back (killed, server restarted).
            return ['state' => 'failed', 'message' => 'The last run stopped unexpectedly. Start it again.'] + $status;
        }

        return is_array($status) ? $status : null;
    }

    /**
     * Claim the run and launch the background process. False when a run is already going.
     */
    public function start(): bool
    {
        if (! $this->claim()) {
            return false;
        }

        try {
            $this->launch(['prices:update', '--claimed']);
        } catch (\Throwable $exception) {
            $this->finish('failed', 'The update could not be started: ' . $exception->getMessage());
            Log::error('Price update could not be started', ['error' => $exception->getMessage()]);

            return false;
        }

        return true;
    }

    /** Take the lock for a run in this process (scheduler or CLI); false when one is running. */
    public function claim(): bool
    {
        if (! Cache::add(self::LOCK_KEY, now()->timestamp, now()->addMinutes(self::LOCK_MINUTES))) {
            return false;
        }
        Cache::forever(self::STATUS_KEY, ['state' => 'running', 'started_at' => now()->toIso8601String()]);

        return true;
    }

    public function finish(string $state, string $message, ?int $updated = null): void
    {
        $previous = (array) Cache::get(self::STATUS_KEY, []);
        Cache::forever(self::STATUS_KEY, array_filter([
            'state' => $state,
            'started_at' => $previous['started_at'] ?? null,
            'finished_at' => now()->toIso8601String(),
            'message' => $message,
            'updated' => $updated,
        ], fn ($value) => $value !== null));
        Cache::forget(self::LOCK_KEY);
    }

    /**
     * Run "php artisan <arguments>" detached from this request, so it keeps going after the
     * response is sent and is not bound by the web time limit.
     */
    protected function launch(array $arguments): void
    {
        $php = (new PhpExecutableFinder())->find(false) ?: 'php';
        $command = implode(' ', array_map('escapeshellarg', array_merge([$php, base_path('artisan')], array_values($arguments))));

        if (PHP_OS_FAMILY === 'Windows') {
            // "start /B" returns at once and leaves the process running in the background.
            pclose(popen('start "" /B ' . $command . ' > NUL 2>&1', 'r'));
        } else {
            exec($command . ' > /dev/null 2>&1 &');
        }
    }
}
