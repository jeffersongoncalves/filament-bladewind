<?php

declare(strict_types=1);

namespace JeffersonGoncalves\Filament\BladeWind\Css;

use Daikazu\BladeWind\Pages\Drivers\FlatDriver;
use Daikazu\BladeWind\Pages\FlatStylesheetSplitter;

/**
 * A Filament 3 panel theme: Tailwind 3 output, so no cascade layers. Filament's `fi-*` component
 * rules sit between the preflight and the utilities as ordinary rules.
 *
 * BladeWind's flat cut already puts every rule from the first class selector on (the `fi-*`
 * components and the utilities) in the per-page pool, in stylesheet order, so this driver only
 * recognises the theme and adds the `fi-*` classes Filament's JavaScript sets at runtime.
 */
final class FilamentDriver extends FlatDriver
{
    public const NAME = 'filament';

    public function __construct(
        FlatStylesheetSplitter $splitter,
        private RuntimeTokens $runtimeTokens,
    ) {
        parent::__construct($splitter);
    }

    public function name(): string
    {
        return self::NAME;
    }

    /**
     * Tailwind 3 (`--tw-*`, no top-level utilities layer) with Filament's `fi-*` components.
     */
    public function detect(string $css): bool
    {
        return str_contains($css, '--tw-')
            && str_contains($css, '.fi-')
            && preg_match('~@layer\s+utilities\s*\{~', $css) !== 1;
    }

    /**
     * The `fi-*` classes Filament's own JavaScript adds to elements, which no view contains.
     */
    public function runtimeTokens(): array
    {
        return $this->runtimeTokens->all();
    }
}
