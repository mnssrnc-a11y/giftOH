<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use App\Services\Security\MalwareScanner;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Anti-malware gate for every upload in the system (request documents, receipts, transfer slips,
 * profile photos). Files are scanned before any controller can store them; an infected file is
 * deleted on the spot, the upload is refused with a clear message, and the attempt is logged for
 * the super admin (Activity log → Security).
 */
class ScanUploadedFiles
{
    public function __construct(
        private MalwareScanner $scanner,
        private AuditLogger $audit
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $errors = [];
        foreach ($this->uploads($request->allFiles()) as $field => $file) {
            // Failed uploads (too large, partial) are reported by the normal validation.
            if (! $file->isValid()) {
                continue;
            }

            $name = $file->getClientOriginalName();
            $result = $this->scanner->scan($file->getPathname(), $name);
            if ($result['clean']) {
                continue;
            }

            @unlink($file->getPathname());
            $base = explode('.', $field)[0];
            $errors[$base][] = "“{$name}” was blocked by the malware scan: {$result['threat']}. Upload a clean JPG, PNG or PDF file.";

            Log::warning('Malware upload blocked', [
                'file' => $name, 'field' => $field, 'threat' => $result['threat'], 'engine' => $result['engine'],
                'path' => $request->path(), 'ip' => $request->ip(), 'user_id' => $request->user()?->getAuthIdentifier(),
            ]);
            $this->audit->record('security', "Upload blocked: {$name} · {$result['threat']}", null, [
                'engine' => $result['engine'], 'page' => '/' . $request->path(), 'ip' => $request->ip(),
            ]);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $next($request);
    }

    /**
     * Flatten nested upload arrays (receipts[], photos[]) to "field.index" => file.
     *
     * @return iterable<string, UploadedFile>
     */
    private function uploads(array $files, string $prefix = ''): iterable
    {
        foreach ($files as $key => $value) {
            $field = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            if ($value instanceof UploadedFile) {
                yield $field => $value;
            } elseif (is_array($value)) {
                yield from $this->uploads($value, $field);
            }
        }
    }
}
