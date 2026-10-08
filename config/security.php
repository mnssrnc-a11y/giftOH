<?php

/*
|--------------------------------------------------------------------------
| Anti-malware scanning of uploaded files
|--------------------------------------------------------------------------
| Every file uploaded to the system (request documents, receipts, transfer slips, profile photos)
| is scanned before any controller sees it (App\Http\Middleware\ScanUploadedFiles). Infected files
| are deleted and the upload is refused. Stored files are re-scanned daily (security:scan-files).
|
| Engines, in order:
|  - heuristic  Built in, always on: blocked/double extensions, executables, scripts hidden in
|               images or PDFs (web shells), PDF JavaScript/launch actions, disguised file types,
|               and the EICAR antivirus test file.
|  - defender   Microsoft Defender Antivirus (MpCmdRun.exe), found automatically on Windows.
|  - clamav     A ClamAV daemon (clamd) over TCP, if you run one.
*/

return [

    'uploads' => [
        'engines' => array_values(array_filter(array_map('trim', explode(',', env('UPLOAD_SCAN_ENGINES', 'heuristic,defender'))))),

        // When an antivirus engine itself fails (not installed, timed out), refuse the upload instead
        // of relying on the remaining engines.
        'fail_closed' => (bool) env('UPLOAD_SCAN_FAIL_CLOSED', false),

        // Only this much of each file is read by the built-in checks (uploads are limited to 10 MB).
        'max_scan_bytes' => 25 * 1024 * 1024,

        // Never accepted anywhere in a file name, e.g. "photo.php.jpg" or "invoice.pdf.exe".
        'blocked_extensions' => [
            'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pht', 'phar', 'phps', 'inc',
            'exe', 'dll', 'com', 'scr', 'msi', 'msp', 'cpl', 'sys', 'bat', 'cmd', 'ps1', 'psm1',
            'vbs', 'vbe', 'js', 'jse', 'mjs', 'wsf', 'wsh', 'hta', 'lnk', 'jar', 'sh', 'bash',
            'cgi', 'pl', 'py', 'rb', 'asp', 'aspx', 'ashx', 'jsp', 'jspx', 'htaccess', 'htm',
            'html', 'xhtml', 'shtml', 'svg', 'swf', 'reg', 'iso', 'docm', 'xlsm', 'pptm',
        ],

        'defender' => [
            // Leave empty to find MpCmdRun.exe automatically.
            'path' => env('DEFENDER_MPCMDRUN_PATH'),
            'timeout' => (int) env('DEFENDER_SCAN_TIMEOUT', 60),
        ],

        'clamav' => [
            'host' => env('CLAMAV_HOST', '127.0.0.1'),
            'port' => (int) env('CLAMAV_PORT', 3310),
            'timeout' => (int) env('CLAMAV_TIMEOUT', 30),
        ],

        // Where the daily scan moves infected stored files (never served to anyone).
        'quarantine_path' => storage_path('app/quarantine'),

        // Folders of stored uploads re-scanned daily: [disk => [folders]].
        'stored' => [
            'local' => ['fund_documents', 'liquidations', 'disbursements'],
            'public' => ['profile_pictures', 'admin_posts'],
        ],
    ],

];
