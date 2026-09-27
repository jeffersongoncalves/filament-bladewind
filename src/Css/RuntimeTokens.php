<?php

declare(strict_types=1);

namespace JeffersonGoncalves\Filament\BladeWind\Css;

use Illuminate\Filesystem\Filesystem;

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
        return $this->tokens ??= $this->scan();
    }

    /**
     * @return list<string>
     */
    private function scan(): array
    {
        if (! $this->files->isDirectory($this->directory)) {
            return [];
        }

        $tokens = [];

        foreach ($this->files->allFiles($this->directory) as $file) {
            if ($file->getExtension() !== 'js') {
                continue;
            }

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
