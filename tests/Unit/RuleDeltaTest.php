<?php

use Daikazu\BladeWind\Pages\UtilityRuleIndex;
use JeffersonGoncalves\Filament\BladeWind\Css\RuleDelta;

function deltaIndex(): UtilityRuleIndex
{
    return UtilityRuleIndex::build(
        '@layer components{.fi-a{color:red}.fi-b{color:blue}.fi-c{color:green}}@layer utilities{.p-1{padding:1px}}'
    );
}

it('is empty when the page already covers every fragment token', function () {
    expect(RuleDelta::build(deltaIndex(), ['fi-a', 'fi-b'], ['fi-a', 'fi-b']))->toBe('');
});

it('streams only the new rule when it comes after everything the fragment already has', function () {
    expect(RuleDelta::build(deltaIndex(), ['fi-a'], ['fi-a', 'fi-c']))
        ->toBe('@layer components{.fi-c{color:green}}');
});

it('re-emits later covered rules of the fragment so a streamed rule cannot win out of order', function () {
    // .fi-b sits after .fi-a in the stylesheet and must still win on an element with both;
    // appending .fi-a alone would put it after .fi-b.
    expect(RuleDelta::build(deltaIndex(), ['fi-b'], ['fi-a', 'fi-b']))
        ->toBe('@layer components{.fi-a{color:red}.fi-b{color:blue}}');
});

it('does not re-emit covered rules of tokens outside the fragment', function () {
    expect(RuleDelta::build(deltaIndex(), ['fi-b', 'p-1'], ['fi-a']))
        ->toBe('@layer components{.fi-a{color:red}}');
});

it('streams everything the fragment needs when the page state is unknown', function () {
    expect(RuleDelta::build(deltaIndex(), [], ['fi-c', 'p-1']))
        ->toBe('@layer components{.fi-c{color:green}}@layer utilities{.p-1{padding:1px}}');
});
