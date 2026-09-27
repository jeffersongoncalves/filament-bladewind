<?php

use Daikazu\BladeWind\Pages\UtilityRuleIndex;
use JeffersonGoncalves\Filament\BladeWind\Css\FilamentDriver;
use JeffersonGoncalves\Filament\BladeWind\Css\RuleDelta;

function filamentTheme(): string
{
    return '*,:after,:before{--tw-ring-offset-width:0px}'
        .'html{line-height:1.5}'
        .'.fi-btn{padding:1rem}.fi-modal{display:grid}.fi-btn-color-danger{color:red}'
        .'.p-1{padding:.25rem}.hidden{display:none}'
        .'@keyframes spin{to{transform:rotate(1turn)}}';
}

it('detects a Filament 3 (Tailwind 3) theme but not a Tailwind 4 build', function () {
    $driver = app(FilamentDriver::class);

    expect($driver->detect(filamentTheme()))->toBeTrue()
        ->and($driver->detect('@layer utilities{.fi-btn{--tw-x:1}}'))->toBeFalse()
        ->and($driver->detect('*{--tw-x:1}.p-1{padding:1px}'))->toBeFalse();
});

it('keeps the preflight in the root and pools the fi-* components with the utilities', function () {
    $split = app(FilamentDriver::class)->split(filamentTheme());

    expect($split->found)->toBeTrue()
        ->and($split->root)->toContain('--tw-ring-offset-width')
        ->and($split->root)->toContain('html{line-height:1.5}')
        ->and($split->root)->not->toContain('.fi-btn')
        ->and($split->utilities)->toContain('.fi-btn{padding:1rem}')
        ->and($split->utilities)->toContain('.p-1{padding:.25rem}');
});

it('emits page rules bare and in stylesheet order', function () {
    $driver = app(FilamentDriver::class);
    $index = UtilityRuleIndex::build($driver->split(filamentTheme())->utilities, $driver->indexTokens(...));

    expect(RuleDelta::build($index, [], ['p-1', 'fi-btn']))->toBe('.fi-btn{padding:1rem}.p-1{padding:.25rem}');
});
