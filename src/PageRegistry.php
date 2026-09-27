<?php

declare(strict_types=1);

namespace JeffersonGoncalves\Filament\BladeWind;

use Illuminate\Contracts\Cache\Repository;

/**
 * The class tokens each loaded page (one browser tab) already carries: its page file's set plus
 * every delta streamed to it since. Keyed by a random id the page sends back on Livewire requests.
 */
class PageRegistry
{
    private const PREFIX = 'filament-bladewind:page:';

    public function __construct(private Repository $cache) {}

    /**
     * @param  list<string>  $tokens
     */
    public function remember(array $tokens, int $ttl): string
    {
        $id = bin2hex(random_bytes(12));
        $this->cache->put(self::PREFIX.$id, array_values(array_unique($tokens)), $ttl);

        return $id;
    }

    /**
     * The tokens recorded for $id; empty when unknown or expired, which only makes the next delta
     * larger (it then re-sends what the page may already have), never incomplete.
     *
     * @return list<string>
     */
    public function covered(string $id): array
    {
        $tokens = $this->cache->get(self::PREFIX.$id);

        return is_array($tokens) ? array_values(array_filter($tokens, is_string(...))) : [];
    }

    /**
     * @param  list<string>  $tokens
     */
    public function add(string $id, array $tokens, int $ttl): void
    {
        $this->cache->put(self::PREFIX.$id, array_values(array_unique([...$this->covered($id), ...$tokens])), $ttl);
    }

    public static function valid(string $id): bool
    {
        return preg_match('~^[0-9a-f]{24}$~', $id) === 1;
    }
}
