<?php

declare(strict_types=1);

namespace JeffersonGoncalves\Filament\BladeWind\Css;

use Daikazu\BladeWind\Stylesheets\CssScanner;

/**
 * Brace-matching over compiled CSS that skips strings, comments and escapes, the same way
 * BladeWind's own scanners do.
 */
final class CssBlocks
{
    /**
     * The first top-level block opened by $prelude, as [start, end (exclusive), inner CSS].
     *
     * @return array{0: int, 1: int, 2: string}|null
     */
    public static function topLevel(string $css, string $prelude): ?array
    {
        $length = strlen($css);
        $depth = 0;
        $pattern = '~\G'.preg_quote($prelude, '~').'\s*\{~';

        for ($i = 0; $i < $length; $i++) {
            $char = $css[$i];

            if ($char === '\\') {
                $i++;

                continue;
            }

            if ($char === '"' || $char === "'") {
                $i = CssScanner::skipString($css, $i, $length) - 1;

                continue;
            }

            if ($char === '/' && $i + 1 < $length && $css[$i + 1] === '*') {
                $i = CssScanner::skipComment($css, $i, $length) - 1;

                continue;
            }

            if ($depth === 0 && $char === '@' && preg_match($pattern, $css, $match, 0, $i) === 1) {
                $open = $i + strlen($match[0]) - 1;
                $close = self::closing($css, $open, $length);

                return $close === null ? null : [$i, $close + 1, substr($css, $open + 1, $close - $open - 1)];
            }

            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
            }
        }

        return null;
    }

    private static function closing(string $css, int $open, int $length): ?int
    {
        $depth = 0;

        for ($i = $open; $i < $length; $i++) {
            $char = $css[$i];

            if ($char === '\\') {
                $i++;

                continue;
            }

            if ($char === '"' || $char === "'") {
                $i = CssScanner::skipString($css, $i, $length) - 1;

                continue;
            }

            if ($char === '/' && $i + 1 < $length && $css[$i + 1] === '*') {
                $i = CssScanner::skipComment($css, $i, $length) - 1;

                continue;
            }

            if ($char === '{') {
                $depth++;
            } elseif ($char === '}' && --$depth === 0) {
                return $i;
            }
        }

        return null;
    }
}
