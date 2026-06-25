<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class VerificationService
{
    /**
     * Verify a generic code against a specific database table.
     *
     * @param string $email The email to check for.
     * @param string $code The code provided by the user.
     * @param string $tableName The table containing the tokens.
     * @param int $expirationMinutes The number of minutes before the code expires.
     * @return array An array containing success status, and error message if failed, or record if success.
     */
    public function verify(string $email, string $code, string $tableName, int $expirationMinutes = 5): array
    {
        $record = DB::table($tableName)
            ->where('email', $email)
            ->first();

        if (!$record) {
            return [
                'success' => false,
                'error' => 'No code found. Please request a new one.'
            ];
        }

        // Check if code has expired
        if (now()->diffInMinutes($record->created_at) > $expirationMinutes) {
            DB::table($tableName)->where('email', $email)->delete();
            return [
                'success' => false,
                'error' => 'This code has expired. Please request a new one.'
            ];
        }

        // Verify the code against the hash
        if (!Hash::check($code, $record->token)) {
            return [
                'success' => false,
                'error' => 'Invalid code. Please try again.'
            ];
        }

        return [
            'success' => true,
            'record' => $record
        ];
    }
}
