<?php

namespace App\Support;

use Illuminate\Validation\Rule;

/**
 * Addresses are in Pampanga: the city / municipality and the barangay are chosen from
 * config/pampanga.php (PSGC list), and the province is always Pampanga.
 */
class Pampanga
{
    public const PROVINCE = 'Pampanga';

    /** @return array<int, string> cities and municipalities */
    public static function towns(): array
    {
        return array_keys(config('pampanga', []));
    }

    /** @return array<int, string> barangays of a city / municipality (none for an unknown one) */
    public static function barangaysOf(?string $town): array
    {
        return $town !== null && is_array($list = config('pampanga')[$town] ?? null) ? $list : [];
    }

    /** Validation rules: the town must be in Pampanga and the barangay must belong to it. */
    public static function rules(?string $town, bool $required = true): array
    {
        $presence = $required ? 'required' : 'nullable';

        return [
            'town' => [$presence, 'string', Rule::in(self::towns())],
            'barangay' => [$presence, 'string', Rule::in(self::barangaysOf($town))],
        ];
    }

    /** "Street, Barangay, Town, Pampanga" without the empty parts. */
    public static function address(?string $street, ?string $barangay, ?string $town): string
    {
        return implode(', ', array_filter([trim((string) $street), $barangay, $town, self::PROVINCE], fn ($part) => filled($part)));
    }
}
