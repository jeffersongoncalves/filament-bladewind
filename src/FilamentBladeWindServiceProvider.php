<?php

declare(strict_types=1);

namespace JeffersonGoncalves\Filament\BladeWind;

use Illuminate\Filesystem\Filesystem;
use JeffersonGoncalves\Filament\BladeWind\Css\RuntimeTokens;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

use function Livewire\on;

class FilamentBladeWindServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('filament-bladewind');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(RuntimeTokens::class, fn ($app): RuntimeTokens => new RuntimeTokens(
            $app->make(Filesystem::class),
            $app->publicPath('js/filament'),
        ));

        $this->app->singleton(PageRegistry::class, fn ($app): PageRegistry => new PageRegistry($app->make('cache.store')));
    }

    public function packageBooted(): void
    {
        on('response', fn (): LivewireDelta => $this->app->make(LivewireDelta::class));
    }
}
