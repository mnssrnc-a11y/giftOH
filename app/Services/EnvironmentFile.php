<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;

/**
 * Updates keys in the application's .env file (used by the super admin's email settings).
 */
class EnvironmentFile
{
    public function __construct(
        private ?string $path = null
    ) {
        $this->path ??= base_path('.env');
    }

    /**
     * @param  array<string, string>  $values  KEY => value
     */
    public function set(array $values): void
    {
        $contents = is_file($this->path) ? file_get_contents($this->path) : '';

        foreach ($values as $key => $value) {
            $line = $key . '=' . $this->quote((string) $value);
            $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';
            $contents = preg_match($pattern, $contents)
                ? preg_replace_callback($pattern, fn () => $line, $contents, 1)
                : rtrim($contents, "\r\n") . PHP_EOL . $line . PHP_EOL;
        }

        // Write to a temp file first so a failure never leaves a half-written .env.
        $temp = $this->path . '.tmp';
        file_put_contents($temp, $contents, LOCK_EX);
        rename($temp, $this->path);

        if (app()->configurationIsCached()) {
            Artisan::call('config:clear');
        }
    }

    /**
     * Single quotes keep the value literal (no ${VAR} expansion); double quotes only when needed.
     */
    private function quote(string $value): string
    {
        if (! str_contains($value, "'")) {
            return "'{$value}'";
        }

        return '"' . str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value) . '"';
    }
}
