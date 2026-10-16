<?php

namespace App\Support;

/**
 * Communities, organizations and parishes connected with SACOP (config/sacop.php): the landing
 * page slideshow, the organizations in the fund request form, and the "Associated with SACOP" mark
 * that staff see on a request from one of them.
 */
class SacopAssociated
{
    /** @return array<int, array{name: string, kind: string, location: ?string, connection: string, source: string, photo: ?string, alt?: string, credit: ?string}> */
    public static function all(): array
    {
        return config('sacop.associated', []);
    }

    /** @return array<int, string> */
    public static function names(): array
    {
        return array_column(self::all(), 'name');
    }

    /** The photo's address: a sacop.ph photo as it is, one of ours (public/images/associated) on this site. */
    public static function photoUrl(array $organization): ?string
    {
        $photo = $organization['photo'] ?? null;

        return $photo === null || str_starts_with($photo, 'https://') ? $photo : asset($photo);
    }

    /** The associated organization a name belongs to (ignoring case and extra spaces), or null. */
    public static function find(?string $organization): ?array
    {
        $key = fn (?string $name): string => mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $name)));
        if ($key($organization) === '') {
            return null;
        }
        foreach (self::all() as $associated) {
            if ($key($associated['name']) === $key($organization)) {
                return $associated;
            }
        }

        return null;
    }
}
