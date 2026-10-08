<?php

namespace App\Http\Controllers;

use App\Services\FundingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Serves the private files attached to a funding request (IDs, certificates, grades, receipts,
 * lists of recipients, proofs of release). Only the requester and admins/super admins may open them,
 * and only files that actually belong to that request.
 */
class FundingFileController extends Controller
{
    public function __construct(
        private FundingService $funding
    ) {
    }

    public function show(string $id, string $path)
    {
        $request = $this->funding->getRequestById($id);
        abort_if($request === null, 404);

        $user = Auth::user();
        $isStaff = $user->isAdmin() || $user->isSuperAdmin();
        abort_unless($isStaff || (string) ($request['user_id'] ?? '') === (string) $user->getAuthIdentifier(), 403);

        // Only paths recorded on this request can be opened (no browsing other files).
        abort_unless(in_array($path, $this->funding->filesOf($request), true), 404);

        foreach ([config('funding.files_disk', 'local'), 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                $mime = (string) Storage::disk($disk)->mimeType($path);
                $headers = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
                if (str_starts_with($mime, 'image/')) {
                    // An image can never run scripts, even if one was hidden in it.
                    $headers['Content-Security-Policy'] = "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox";
                }
                // Only images and PDFs open in the browser; anything else is downloaded, never rendered.
                $disposition = str_starts_with($mime, 'image/') || $mime === 'application/pdf' ? 'inline' : 'attachment';

                return Storage::disk($disk)->response($path, basename($path), $headers, $disposition);
            }
        }

        abort(404);
    }
}
