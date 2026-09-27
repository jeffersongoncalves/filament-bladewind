<?php

use Daikazu\BladeWind\Pages\UtilityRuleIndex;
use JeffersonGoncalves\Filament\BladeWind\Css\FilamentDriver;
use JeffersonGoncalves\Filament\BladeWind\Css\RuleDelta;

function filamentTheme(): string
{
    return '@layer theme,base,components,utilities;'
        .'@layer theme{:root{--color-primary:red}}'
        .'@layer base{*{margin:0}}'
        .'@layer components{.fi-btn{padding:1rem}.fi-modal{display:grid}.fi-btn-color-danger{color:var(--color-primary)}}'
        .'@layer utilities{.p-1{padding:.25rem}.hidden{display:none}}'
        .'@keyframes spin{to{transform:rotate(360deg)}}';
}

it('detects a Filament theme but not a plain Tailwind build', function () {
    $driver = app(FilamentDriver::class);

    expect($driver->detect(filamentTheme()))->toBeTrue()
        ->and($driver->detect('@layer utilities{.p-1{padding:.25rem}}'))->toBeFalse();
});

it('moves the components layer out of the root and into the per-page pool', function () {
    $split = app(FilamentDriver::class)->split(filamentTheme());

    expect($split->found)->toBeTrue()
        ->and($split->root)->toContain('@layer components;')
        ->and($split->root)->toContain('@layer base{*{margin:0}}')
        ->and($split->root)->toContain('--color-primary:red')
        ->and($split->root)->toContain('@keyframes spin')
        ->and($split->root)->not->toContain('.fi-btn')
        ->and($split->utilities)->toStartWith('@layer components{.fi-btn{padding:1rem}')
        ->and($split->utilities)->toContain('@layer utilities{.p-1{padding:.25rem}');
});

it('emits page rules in their own layers so the cascade order is kept', function () {
    $driver = app(FilamentDriver::class);
    $index = UtilityRuleIndex::build($driver->split(filamentTheme())->utilities, $driver->indexTokens(...));

    $css = RuleDelta::build($index, [], ['fi-btn', 'p-1']);

    expect($css)->toBe('@layer components{.fi-btn{padding:1rem}}@layer utilities{.p-1{padding:.25rem}}');
});
