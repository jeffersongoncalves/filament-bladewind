<?php

declare(strict_types=1);

namespace JeffersonGoncalves\Filament\BladeWind\Http\Middleware;

use Closure;
use Daikazu\BladeWind\Declarations\Safelist;
use Daikazu\BladeWind\Discovery\ViewLocator;
use Daikazu\BladeWind\Integration\PathFilter;
use Daikazu\BladeWind\Pages\ClassSetBuilder;
use Daikazu\BladeWind\Pages\Drivers\DriverSelector;
use Daikazu\BladeWind\Pages\PageStyles;
use Daikazu\BladeWind\Pages\RenderedViews;
use Daikazu\BladeWind\Pages\StylesDirective;
use Daikazu\BladeWind\Stylesheets\StylesheetIndex;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use JeffersonGoncalves\Filament\BladeWind\BladeWindPlugin;
use JeffersonGoncalves\Filament\BladeWind\Css\FilamentDriver;
use JeffersonGoncalves\Filament\BladeWind\Css\RuleDelta;
use JeffersonGoncalves\Filament\BladeWind\PageRegistry;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Points BladeWind at the panel theme for this request and, on a full page load, records the
 * page's class set so its Livewire updates can be streamed only the rules the page lacks.
 *
 * Registered as persistent panel middleware, so it also runs (before the component) on Livewire
 * updates; Livewire runs persistent middleware against a placeholder response, which is why the
 * update side is handled by the `response` listener in the service provider instead.
 */
class ApplyBladeWind
{
    public const HEADER = 'X-Filament-BladeWind';

    public const META = 'filament-bladewind';

    public function __construct(private PageRegistry $pages) {}

    public function handle(Request $request, Closure $next, string $panel): SymfonyResponse
    {
        $plugin = Filament::getPanel($panel)->getPlugin(BladeWindPlugin::ID);

        if (! $plugin instanceof BladeWindPlugin || blank($plugin->getTheme())) {
            return $next($request);
        }

        self::configure($plugin);
        app()->instance(BladeWindPlugin::class.'.active', $plugin);

        $response = $next($request);

        if ($response instanceof Response && is_string($content = $response->getContent()) && str_contains($content, StylesDirective::MARKER)) {
            $response->setContent($this->withPageId($content, $plugin));
        }

        return $response;
    }

    /**
     * bladewind.* is global (a public site may split its own stylesheet), and the services reading
     * it are singletons, so they are dropped and rebuilt against the panel theme.
     */
    public static function configure(BladeWindPlugin $plugin): void
    {
        config([
            'bladewind.stylesheets' => [$plugin->getTheme()],
            'bladewind.paths' => array_values(array_unique([
                ...(array) config('bladewind.paths', []),
                resource_path('views'),
                ...(glob(base_path('vendor/filament/*/resources/views'), GLOB_ONLYDIR) ?: []),
                ...$plugin->getPaths(),
            ])),
            // Package views outside the paths (third-party plugins) contribute their rendered
            // classes; anything a later update renders is streamed by the delta.
            'bladewind.pages.unanalysed' => 'html',
            'bladewind.drivers' => array_values(array_unique([...(array) config('bladewind.drivers', []), FilamentDriver::class])),
            'bladewind.framework' => FilamentDriver::NAME,
            'bladewind.safelist' => array_values(array_unique([...(array) config('bladewind.safelist', []), ...$plugin->getSafelist()])),
        ]);

        foreach ([
            StylesheetIndex::class, PageStyles::class, StylesDirective::class, PathFilter::class,
            ViewLocator::class, ClassSetBuilder::class, DriverSelector::class, Safelist::class, RuleDelta::class,
        ] as $service) {
            app()->forgetInstance($service);
        }
    }

    private function withPageId(string $html, BladeWindPlugin $plugin): string
    {
        $id = $this->pages->remember(
            app(ClassSetBuilder::class)->build($html, app(RenderedViews::class)->paths(), app(FilamentDriver::class)->runtimeTokens())->tokens,
            $plugin->getTtl(),
        );

        $meta = '<meta name="'.self::META.'" content="'.$id.'">';
        $head = stripos($html, '</head>');

        return $head === false ? $html : substr_replace($html, $meta, $head, 0);
    }

    /**
     * Sends the page id with every Livewire request and adds each streamed delta to the head
     * before the update morphs the DOM, so new markup never paints unstyled.
     */
    public static function script(): string
    {
        $meta = self::META;
        $header = self::HEADER;

        return <<<HTML
            <script>
                document.addEventListener('livewire:init', () => {
                    const page = document.querySelector('meta[name="{$meta}"]')?.content;

                    if (! page) return;

                    Livewire.hook('request', ({ options }) => {
                        options.headers['{$header}'] = page;
                    });

                    // Livewire 3 awaits this hook before it morphs the returned components.
                    Livewire.hook('payload.intercept', ({ components }) => {
                        for (const component of components ?? []) {
                            const css = component?.effects?.bladewind;

                            if (! css) continue;

                            const style = document.createElement('style');
                            style.dataset.filamentBladewind = '';
                            style.textContent = css;
                            document.head.append(style);
                        }
                    });
                });
            </script>
            HTML;
    }
}
