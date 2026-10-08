<?php

namespace App\Services\Security;

/**
 * An antivirus engine could not run (not installed, not reachable, timed out).
 */
class ScannerUnavailable extends \RuntimeException
{
}
