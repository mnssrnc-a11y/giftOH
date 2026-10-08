<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

/**
 * Serves /storage/{path} (profile pictures, admin post images) when the public disk is not on the
 * server's own drive, e.g. FILES_DRIVER=firebase on Render. With the normal local disk the web
 * server answers these URLs straight from public/storage and this controller is never reached.
 */
class PublicFileController extends Controller
{
    public function show(string $path)
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        abort_if($path === '' || str_contains($path, '..'), 404);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404);

        $mime = (string) $disk->mimeType($path);
        $headers = [
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
            // Public files are pictures; nothing in them may ever run as a page.
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
        ];
        $disposition = str_starts_with($mime, 'image/') || $mime === 'application/pdf' ? 'inline' : 'attachment';

        return $disk->response($path, basename($path), $headers, $disposition);
    }
}
