<?php

declare(strict_types=1);

namespace JeffersonGoncalves\Filament\BladeWind\Css;

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Finder\SplFileInfo;

/**
 * The `fi-*` class names found as string literals in Filament's published JavaScript
 * (`public/js/filament`): classes its components add with `classList`, which no Blade view and
 * no rendered HTML contains. They join every page's class set so their rules are always there.
 *
 * ponytail: a literal scan; a class built by concatenation ('fi-' + name) is invisible to it and
 * belongs on the plugin's safelist.
 */
final class RuntimeTokens
{
    /** @var list<string>|null */
    private ?array $tokens = null;

    public function __construct(
        private Filesystem $files,
        private string $directory,
    ) {}

    /**
     * @return list<string>
     */
    public function all(): array
    {
        if ($this->tokens !== null) {
            return $this->tokens;
        }

        if (! $this->files->isDirectory($this->directory)) {
            return $this->tokens = [];
        }

        $scripts = array_values(array_filter(
            $this->files->allFiles($this->directory),
            static fn ($file): bool => $file->getExtension() === 'js',
        ));

        // Re-scanned only when a script changes (filament:upgrade republishes them): reading
        // ~3 MB of JavaScript on every panel request costs tens of milliseconds.
        $version = count($scripts).':'.max([0, ...array_map(static fn ($file): int => (int) $file->getMTime(), $scripts)]);

        return $this->tokens = Memo::get('runtime-tokens:'.md5($this->directory), $version, fn (): array => $this->scan($scripts));
    }

    /**
     * @param  list<SplFileInfo>  $scripts
     * @return list<string>
     */
    private function scan(array $scripts): array
    {
        $tokens = [];

        foreach ($scripts as $file) {
            preg_match_all('~["\'`]([^"\'`\n]*)["\'`]~', $file->getContents(), $literals);

            foreach ($literals[1] as $literal) {
                preg_match_all('~(?<![\w-])fi-[a-z0-9]+(?:-[a-z0-9]+)*(?![\w-])~', $literal, $classes);

                foreach ($classes[0] as $class) {
                    $tokens[$class] = true;
                }
            }
        }

        $tokens = array_keys($tokens);
        sort($tokens);

        return $tokens;
    }
}
