<?php

use Filament\Facades\Filament;
use JeffersonGoncalves\Filament\BladeWind\BladeWindPlugin;
use JeffersonGoncalves\Filament\BladeWind\Css\FilamentDriver;
use JeffersonGoncalves\Filament\BladeWind\Http\Middleware\ApplyBladeWind;
use JeffersonGoncalves\Filament\BladeWind\PageRegistry;
use Livewire\Livewire;

it('replaces the panel theme and adds its persistent middleware', function () {
    $panel = Filament::getPanel('admin');

    expect($panel->getTheme()->getId())->toBe('filament-bladewind')
        ->and($panel->getMiddleware())->toContain(ApplyBladeWind::class.':admin')
        // By class alone: Livewire compares it against the route entry minus its `:admin`.
        ->and(Livewire::getPersistentMiddleware())->toContain(ApplyBladeWind::class)
        ->and(Livewire::getPersistentMiddleware())->not->toContain(ApplyBladeWind::class.':admin');
});

it('points BladeWind at the panel theme and the Filament driver', function () {
    ApplyBladeWind::configure(Filament::getPanel('admin')->getPlugin(BladeWindPlugin::ID));

    expect(config('bladewind.stylesheets'))->toBe(['resources/css/filament/admin/theme.css'])
        ->and(config('bladewind.framework'))->toBe(FilamentDriver::NAME)
        ->and(config('bladewind.drivers'))->toContain(FilamentDriver::class)
        ->and(config('bladewind.pages.unanalysed'))->toBe('html')
        ->and(config('bladewind.safelist'))->toContain('fi-custom-*');
});

it('keeps what a page covers and grows it with each delta', function () {
    $pages = app(PageRegistry::class);
    $id = $pages->remember(['fi-a', 'fi-a', 'fi-b'], 60);

    $pages->add($id, ['fi-c'], 60);

    expect(PageRegistry::valid($id))->toBeTrue()
        ->and($pages->covered($id))->toBe(['fi-a', 'fi-b', 'fi-c'])
        ->and($pages->covered(str_repeat('0', 24)))->toBe([])
        ->and(PageRegistry::valid('../etc'))->toBeFalse();
});

it('sends the page id with Livewire requests and adds streamed CSS before the morph', function () {
    expect(ApplyBladeWind::script())
        ->toContain('Livewire.interceptRequest')
        ->toContain("request.options.headers['".ApplyBladeWind::HEADER."']")
        ->toContain('payload?.effects?.bladewind');
});
