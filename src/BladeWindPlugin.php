<?php

declare(strict_types=1);

namespace JeffersonGoncalves\Filament\BladeWind;

use Daikazu\BladeWind\Pages\StylesDirective;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Assets\Theme;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\HtmlString;
use JeffersonGoncalves\Filament\BladeWind\Http\Middleware\ApplyBladeWind;

class BladeWindPlugin implements Plugin
{
    public const ID = 'filament-bladewind';

    protected ?string $theme = null;

    /** @var list<string> */
    protected array $paths = [];

    /** @var list<string> */
    protected array $safelist = [];

    protected int $ttl = 43200;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return self::ID;
    }

    /**
     * The panel's Vite theme entry, i.e. what ->viteTheme() would receive. Replaces ->viteTheme().
     */
    public function theme(string $theme): static
    {
        $this->theme = $theme;

        return $this;
    }

    public function getTheme(): ?string
    {
        return $this->theme;
    }

    /**
     * Extra view directories to analyse (third-party plugins' resources/views). The panel's own
     * views and vendor/filament/* are always included.
     *
     * @param  list<string>  $paths
     */
    public function paths(array $paths): static
    {
        $this->paths = $paths;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getPaths(): array
    {
        return $this->paths;
    }

    /**
     * Classes every page carries whatever the analysis sees: exact tokens or `prefix-*` patterns.
     *
     * @param  list<string>  $safelist
     */
    public function safelist(array $safelist): static
    {
        $this->safelist = $safelist;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getSafelist(): array
    {
        return $this->safelist;
    }

    /**
     * How long (seconds) a loaded page's class set is kept for its Livewire updates.
     */
    public function ttl(int $seconds): static
    {
        $this->ttl = $seconds;

        return $this;
    }

    public function getTtl(): int
    {
        return $this->ttl;
    }

    public function register(Panel $panel): void
    {
        $panel
            ->theme(Theme::make('filament-bladewind')->html(
                static fn (): HtmlString => new HtmlString(app(StylesDirective::class)->render()),
            ))
            ->middleware([ApplyBladeWind::class.':'.$panel->getId()])
            // Livewire matches persistent middleware by class (ignoring `:args`) and re-applies the
            // panel route's entry, so the class alone makes updates run it with this panel's id.
            ->persistentMiddleware([ApplyBladeWind::class])
            ->renderHook(PanelsRenderHook::BODY_END, static fn (): HtmlString => new HtmlString(ApplyBladeWind::script()));
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
