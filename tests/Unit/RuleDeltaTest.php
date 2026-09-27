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

it('re-emits every later rule the page has, not only the fragment\'s, so elements outside it keep their cascade', function () {
    // The page has .fi-icon-btn (display:flex) and, later, the topbar rule hiding its close
    // button. A lazy component's update streams a new earlier rule and re-emits .fi-icon-btn:
    // unless the topbar rule is re-emitted after it too, display:flex now wins on a button the
    // update never rendered.
    $index = UtilityRuleIndex::build(
        '@layer components{.fi-new{color:red}.fi-icon-btn{display:flex}@media (width>=64rem){.fi-topbar-close-sidebar-btn{display:none}}}'
    );

    expect(RuleDelta::build($index, ['fi-icon-btn', 'fi-topbar-close-sidebar-btn'], ['fi-new', 'fi-icon-btn']))
        ->toBe('@layer components{.fi-new{color:red}.fi-icon-btn{display:flex}}@layer components{@media (width>=64rem){.fi-topbar-close-sidebar-btn{display:none}}}');
});

it('leaves out page rules that come before the first new one', function () {
    expect(RuleDelta::build(deltaIndex(), ['fi-a', 'p-1'], ['fi-c']))
        ->toBe('@layer components{.fi-c{color:green}}@layer utilities{.p-1{padding:1px}}');
});

it('streams everything the fragment needs when the page state is unknown', function () {
    expect(RuleDelta::build(deltaIndex(), [], ['fi-c', 'p-1']))
        ->toBe('@layer components{.fi-c{color:green}}@layer utilities{.p-1{padding:1px}}');
});
