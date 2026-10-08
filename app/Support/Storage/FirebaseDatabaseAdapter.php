<?php

namespace App\Support\Storage;

use App\Services\FirebaseService;
use Illuminate\Support\Facades\Cache;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToWriteFile;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use Throwable;

/**
 * Keeps uploaded files inside the Firebase Realtime Database, so they survive on hosts whose disk
 * is wiped on every restart (Render's free plan) without paying for Cloud Storage.
 *
 * Layout under the configured root (for example file_store/local):
 *   meta/{sha1(path)}   = { path, size, mime, chunks, visibility, updated_at }
 *   chunks/{sha1(path)} = { c0000: base64, c0001: base64, ... }   (1 MB of file per chunk)
 *
 * Only the server (Admin SDK) can read these nodes; the database rules deny everyone else.
 * Laravel code keeps using Storage::disk('local') / Storage::disk('public') unchanged.
 */
class FirebaseDatabaseAdapter implements FilesystemAdapter
{
    private const CHUNK_BYTES = 1024 * 1024;

    private const META_CACHE_SECONDS = 600;

    /** @var array<string, array|null> per-request metadata memo */
    private array $memo = [];

    /**
     * @param  FirebaseService|object  $firebase  anything with getDatabase() (tests pass a fake)
     */
    public function __construct(
        private object $firebase,
        private string $root = 'file_store/local',
        private string $defaultVisibility = 'private'
    ) {
        $this->root = trim($this->root, '/');
    }

    public function fileExists(string $path): bool
    {
        return $this->meta($path) !== null;
    }

    public function directoryExists(string $path): bool
    {
        $prefix = $this->normalize($path) . '/';

        foreach ($this->allMeta() as $meta) {
            if (str_starts_with((string) ($meta['path'] ?? ''), $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $path = $this->normalize($path);
        $key = $this->key($path);

        try {
            $chunks = [];
            foreach (str_split($contents === '' ? '' : $contents, self::CHUNK_BYTES) as $index => $part) {
                $chunks[sprintf('c%04d', $index)] = base64_encode($part);
            }

            $mime = (new FinfoMimeTypeDetector())->detectMimeType($path, $contents) ?? 'application/octet-stream';
            $meta = [
                'path' => $path,
                'size' => strlen($contents),
                'mime' => $mime,
                'chunks' => count($chunks),
                'visibility' => (string) ($config->get('visibility') ?? $this->defaultVisibility),
                'updated_at' => time(),
            ];

            // Chunks first, metadata last: a file only "exists" once all of its bytes are saved.
            $this->db()->getReference("{$this->root}/chunks/{$key}")->set($chunks === [] ? null : $chunks);
            $this->db()->getReference("{$this->root}/meta/{$key}")->set($meta);
            $this->remember($path, $meta);
        } catch (Throwable $exception) {
            throw UnableToWriteFile::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $data = stream_get_contents($contents);
        if ($data === false) {
            throw UnableToWriteFile::atLocation($path, 'Could not read the upload stream.');
        }

        $this->write($path, $data, $config);
    }

    public function read(string $path): string
    {
        $path = $this->normalize($path);
        $meta = $this->meta($path);
        if ($meta === null) {
            throw UnableToReadFile::fromLocation($path, 'File not found.');
        }

        try {
            $chunks = $this->db()->getReference("{$this->root}/chunks/{$this->key($path)}")->getValue();
        } catch (Throwable $exception) {
            throw UnableToReadFile::fromLocation($path, $exception->getMessage(), $exception);
        }

        if (! is_array($chunks)) {
            return '';
        }

        ksort($chunks, SORT_STRING);
        $contents = '';
        foreach ($chunks as $chunk) {
            $contents .= base64_decode((string) $chunk, true) ?: '';
        }

        return $contents;
    }

    public function readStream(string $path)
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $this->read($path));
        rewind($stream);

        return $stream;
    }

    public function delete(string $path): void
    {
        $path = $this->normalize($path);
        $key = $this->key($path);

        try {
            $this->db()->getReference("{$this->root}/meta/{$key}")->remove();
            $this->db()->getReference("{$this->root}/chunks/{$key}")->remove();
            $this->remember($path, null);
        } catch (Throwable $exception) {
            throw UnableToDeleteFile::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function deleteDirectory(string $path): void
    {
        $prefix = $this->normalize($path) . '/';

        foreach ($this->allMeta() as $meta) {
            if (str_starts_with((string) ($meta['path'] ?? ''), $prefix)) {
                $this->delete($meta['path']);
            }
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        // Folders are implied by file paths; nothing to create.
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $path = $this->normalize($path);
        $meta = $this->meta($path) ?? throw UnableToRetrieveMetadata::visibility($path, 'File not found.');
        $meta['visibility'] = $visibility;
        $this->db()->getReference("{$this->root}/meta/{$this->key($path)}/visibility")->set($visibility);
        $this->remember($path, $meta);
    }

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path, null, $this->metaOrFail($path, 'visibility')['visibility'] ?? $this->defaultVisibility);
    }

    public function mimeType(string $path): FileAttributes
    {
        return new FileAttributes($path, null, null, null, $this->metaOrFail($path, 'mimeType')['mime'] ?? 'application/octet-stream');
    }

    public function lastModified(string $path): FileAttributes
    {
        return new FileAttributes($path, null, null, (int) ($this->metaOrFail($path, 'lastModified')['updated_at'] ?? time()));
    }

    public function fileSize(string $path): FileAttributes
    {
        return new FileAttributes($path, (int) ($this->metaOrFail($path, 'fileSize')['size'] ?? 0));
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $base = $this->normalize($path);
        $prefix = $base === '' ? '' : $base . '/';
        $directories = [];

        foreach ($this->allMeta() as $meta) {
            $file = (string) ($meta['path'] ?? '');
            if ($file === '' || ($prefix !== '' && ! str_starts_with($file, $prefix))) {
                continue;
            }

            $relative = substr($file, strlen($prefix));
            $parts = explode('/', $relative);

            // Every folder between the listed path and the file.
            for ($depth = 1; $depth < count($parts); $depth++) {
                if (! $deep && $depth > 1) {
                    break;
                }
                $directories[$prefix . implode('/', array_slice($parts, 0, $depth))] = true;
            }

            if ($deep || count($parts) === 1) {
                yield new FileAttributes($file, (int) ($meta['size'] ?? 0), $meta['visibility'] ?? null, (int) ($meta['updated_at'] ?? 0), $meta['mime'] ?? null);
            }
        }

        foreach (array_keys($directories) as $directory) {
            yield new DirectoryAttributes($directory);
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $this->copy($source, $destination, $config);
            $this->delete($source);
        } catch (Throwable $exception) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $exception);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $this->write($destination, $this->read($source), $config);
        } catch (Throwable $exception) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $exception);
        }
    }

    private function meta(string $path): ?array
    {
        $path = $this->normalize($path);
        if (array_key_exists($path, $this->memo)) {
            return $this->memo[$path];
        }

        $key = $this->key($path);
        $meta = Cache::remember($this->cacheKey($key), self::META_CACHE_SECONDS, function () use ($key) {
            // Cache "missing" as false so absent files don't hit Firebase on every page.
            return $this->db()->getReference("{$this->root}/meta/{$key}")->getValue() ?? false;
        });

        return $this->memo[$path] = is_array($meta) ? $meta : null;
    }

    private function metaOrFail(string $path, string $attribute): array
    {
        return $this->meta($path) ?? throw new UnableToRetrieveMetadata("Unable to retrieve the {$attribute} for file at location: {$path}. File not found.");
    }

    /** @return array<int, array> */
    private function allMeta(): array
    {
        $all = $this->db()->getReference("{$this->root}/meta")->getValue();

        return is_array($all) ? array_values(array_filter($all, 'is_array')) : [];
    }

    private function remember(string $path, ?array $meta): void
    {
        $this->memo[$path] = $meta;
        Cache::put($this->cacheKey($this->key($path)), $meta ?? false, self::META_CACHE_SECONDS);
    }

    private function cacheKey(string $key): string
    {
        return 'rtdb-file:' . $this->root . ':' . $key;
    }

    private function key(string $path): string
    {
        return sha1($this->normalize($path));
    }

    private function normalize(string $path): string
    {
        return trim(str_replace('\\', '/', $path), '/');
    }

    private function db(): object
    {
        return $this->firebase->getDatabase();
    }
}
