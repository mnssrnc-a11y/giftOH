<?php

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Every field people type in shows an example (placeholder): "e.g. 09171234567", "e.g. Juan".
 * Not needed for fields a browser draws its own way (date, file, checkbox…) or fields that only
 * show a value (read-only), except the amount set with the quick-amount buttons.
 */
class PlaceholderTest extends TestCase
{
    private const NO_EXAMPLE = ['hidden', 'checkbox', 'radio', 'file', 'date', 'datetime-local', 'time', 'month', 'week', 'submit', 'button', 'reset', 'range', 'color', 'image'];

    /** The opening tags starting with $start, ending at the first ">" that is not part of "->" or "=>". */
    private function tags(string $source, string $start): array
    {
        $tags = [];
        $offset = 0;
        while (($at = strpos($source, $start, $offset)) !== false) {
            $end = $at + strlen($start);
            while ($end < strlen($source) && ! ($source[$end] === '>' && ! in_array($source[$end - 1], ['-', '='], true))) {
                $end++;
            }
            $tags[] = [substr($source, $at, $end - $at + 1), substr_count($source, "\n", 0, $at) + 1];
            $offset = $end;
        }

        return $tags;
    }

    public function test_every_typed_field_has_an_example(): void
    {
        $missing = [];
        $files = (new Finder)->files()->in([resource_path('views'), resource_path('js')])->name(['*.blade.php', '*.js']);
        foreach ($files as $file) {
            $path = str_replace('\\', '/', $file->getRelativePathname());
            if ($path === 'components/fr-field.blade.php') {
                continue; // its placeholder comes from each <x-fr-field> (checked below)
            }
            $source = $file->getContents();
            foreach (['<input', '<textarea'] as $start) {
                foreach ($this->tags($source, $start) as [$tag, $line]) {
                    if (! preg_match('/^<(input|textarea)[\s>]/', $tag)) {
                        continue;
                    }
                    $type = preg_match('/\stype="([^"]+)"/', $tag, $m) ? strtolower($m[1]) : 'text';
                    $readOnly = (bool) preg_match('/\s(readonly|disabled)[\s>=]/', $tag) && ! str_contains($tag, 'data-amount-buttons');
                    if (($start === '<input' && in_array($type, self::NO_EXAMPLE, true)) || $readOnly) {
                        continue;
                    }
                    if (! preg_match('/\splaceholder\s*=/', $tag)) {
                        $missing[] = "{$path}:{$line}";
                    }
                    $this->assertLessThanOrEqual(1, preg_match_all('/\splaceholder\s*=/', $tag), "{$path}:{$line} has two placeholders");
                }
            }
            // Fund request form fields drawn by the component (those without their own control inside).
            foreach ($this->tags($source, '<x-fr-field') as [$tag, $line]) {
                $type = preg_match('/\stype="([^"]+)"/', $tag, $m) ? $m[1] : 'text';
                if (str_ends_with(rtrim($tag), '/>') && ! in_array($type, self::NO_EXAMPLE, true) && ! preg_match('/\splaceholder\s*=/', $tag)) {
                    $missing[] = "{$path}:{$line} (x-fr-field)";
                }
            }
        }

        $this->assertSame([], $missing, 'Fields without an example (placeholder): ' . implode(', ', $missing));
    }
}
