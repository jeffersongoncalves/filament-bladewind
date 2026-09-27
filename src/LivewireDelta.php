<?php

declare(strict_types=1);

namespace JeffersonGoncalves\Filament\BladeWind;

use Daikazu\BladeWind\Pages\ClassSetBuilder;
use Daikazu\BladeWind\Pages\RenderedViews;
use JeffersonGoncalves\Filament\BladeWind\Css\RuleDelta;
use JeffersonGoncalves\Filament\BladeWind\Http\Middleware\ApplyBladeWind;
use Throwable;

/**
 * Livewire `response` finisher: adds to the update payload the CSS its rendered HTML needs and
 * the page does not have yet, as `effects.bladewind` on the first component that rendered.
 */
class LivewireDelta
{
    public function __construct(private PageRegistry $pages) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function __invoke(array $payload): array
    {
        $plugin = app()->bound(BladeWindPlugin::class.'.active') ? app(BladeWindPlugin::class.'.active') : null;
        $page = (string) request()->header(ApplyBladeWind::HEADER, '');

        if (! $plugin instanceof BladeWindPlugin || ! PageRegistry::valid($page) || ! is_array($payload['components'] ?? null)) {
            return $payload;
        }

        $html = '';
        $target = null;

        foreach ($payload['components'] as $key => $component) {
            $fragment = self::markup($component['effects'] ?? null);

            if ($fragment !== '') {
                $html .= $fragment;
                $target ??= $key;
            }
        }

        if ($target === null) {
            return $payload;
        }

        try {
            $tokens = app(ClassSetBuilder::class)->build($html, app(RenderedViews::class)->paths())->tokens;
            $css = app(RuleDelta::class)->css($this->pages->covered($page), $tokens);
        } catch (Throwable $exception) {
            // A page without its delta is a styling glitch; a failed update is a broken panel.
            report($exception);

            return $payload;
        }

        if ($css !== '') {
            $payload['components'][$target]['effects']['bladewind'] = $css;
        }

        $this->pages->add($page, $tokens, $plugin->getTtl());

        return $payload;
    }

    /**
     * Every piece of HTML an update morphs in: the component's `html`, and the `partials` and
     * `islands` Livewire 4 renders on their own (Filament 5 sends its action modals as a partial).
     */
    public static function markup(mixed $effects): string
    {
        if (! is_array($effects)) {
            return '';
        }

        $html = is_string($effects['html'] ?? null) ? $effects['html'] : '';

        foreach (['partials', 'islands'] as $key) {
            $parts = $effects[$key] ?? null;

            if (! is_array($parts)) {
                continue;
            }

            array_walk_recursive($parts, static function (mixed $value) use (&$html): void {
                if (is_string($value) && str_contains($value, '<')) {
                    $html .= $value;
                }
            });
        }

        return $html;
    }
}
