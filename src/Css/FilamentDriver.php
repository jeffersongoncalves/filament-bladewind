<?php

declare(strict_types=1);

namespace JeffersonGoncalves\Filament\BladeWind\Css;

use Daikazu\BladeWind\Pages\Drivers\CssFrameworkDriver;
use Daikazu\BladeWind\Pages\Drivers\Tailwind4Driver;
use Daikazu\BladeWind\Pages\SplitStylesheet;

/**
 * A Filament panel theme: Tailwind 4 output whose bulk (~90%) is the `fi-*` rules of
 * `@layer components`, which BladeWind's stock Tailwind 4 driver keeps whole in the shared root.
 *
 * This driver moves the components layer into the per-page pool as well. Each pooled rule keeps
 * its own `@layer components` / `@layer utilities` wrapper (a wrapper at-rule to BladeWind's rule
 * index), so a page file re-opens both top-level layers and the root's layer-order statement
 * keeps them in their original cascade position.
 *
 * The support layers (theme variables, `--tw-*` properties, `@property`, `@keyframes`) stay whole
 * in the root: they are a few KB, and rules streamed to a Livewire update later then never depend
 * on a variable the page did not load.
 */
final class FilamentDriver implements CssFrameworkDriver
{
    public const NAME = 'filament';

    private const COMPONENTS = '@layer components';

    public function __construct(
        private Tailwind4Driver $tailwind,
        private RuntimeTokens $runtimeTokens,
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function detect(string $css): bool
    {
        return $this->tailwind->detect($css) && str_contains($css, '.fi-');
    }

    public function split(string $css): SplitStylesheet
    {
        $split = $this->tailwind->split($css);

        if (! $split->found) {
            return $split;
        }

        $root = $split->rootWithSupport();
        $block = CssBlocks::topLevel($root, self::COMPONENTS);
        $components = '';

        if ($block !== null) {
            [$start, $end, $components] = $block;
            $root = substr_replace($root, self::COMPONENTS.';', $start, $end - $start);
        }

        return new SplitStylesheet(
            root: $root,
            utilities: ($components === '' ? '' : self::COMPONENTS.'{'.$components.'}')
                .SplitStylesheet::UTILITIES_PRELUDE.'{'.$split->utilities.'}',
            found: true,
            supportShaken: false,
            unshakenReason: 'kept whole by the filament driver so Livewire updates can stream rules',
        );
    }

    public function indexTokens(string $selectorList): array
    {
        return $this->tailwind->indexTokens($selectorList);
    }

    /**
     * Every pooled rule already carries its layer wrapper, so page rules are emitted as they are.
     */
    public function wrapUtilities(string $rules): string
    {
        return $rules;
    }

    public function expects(): string
    {
        return $this->tailwind->expects();
    }

    /**
     * The `fi-*` classes Filament's own JavaScript adds to elements, which no view contains.
     */
    public function runtimeTokens(): array
    {
        return $this->runtimeTokens->all();
    }
}
