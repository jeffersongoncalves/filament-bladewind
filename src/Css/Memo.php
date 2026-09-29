<?php

declare(strict_types=1);

namespace JeffersonGoncalves\Filament\BladeWind\Css;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * A computed value kept in the cache until its source changes, under one key per value so an
 * outdated entry is overwritten instead of piling up: the stored version (a content hash, a
 * file mtime) is compared on every read, and a mismatch recomputes and replaces it.
 *
 * BladeWind memoises its stylesheet work per PHP process, which under PHP-FPM means per request;
 * splitting and indexing a Filament theme costs hundreds of milliseconds, so it is shared here.
 */
final class Memo
{
    private const PREFIX = 'filament-bladewind:';

    /**
     * @template T
     *
     * @param  Closure(): T  $compute
     * @return T
     */
    public static function get(string $key, string $version, Closure $compute): mixed
    {
        $cached = Cache::get(self::PREFIX.$key);

        if (is_array($cached) && ($cached['version'] ?? null) === $version) {
            return $cached['value'];
        }

        $value = $compute();
        Cache::forever(self::PREFIX.$key, ['version' => $version, 'value' => $value]);

        return $value;
    }
}
