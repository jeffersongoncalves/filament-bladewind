<div class="filament-hidden">

![Filament BladeWind](https://raw.githubusercontent.com/jeffersongoncalves/filament-bladewind/3.x/art/jeffersongoncalves-filament-bladewind.png)

</div>

# Filament BladeWind

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jeffersongoncalves/filament-bladewind.svg)](https://packagist.org/packages/jeffersongoncalves/filament-bladewind)
[![Tests](https://github.com/jeffersongoncalves/filament-bladewind/actions/workflows/tests.yml/badge.svg)](https://github.com/jeffersongoncalves/filament-bladewind/actions/workflows/tests.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE.md)

Per-page CSS for Filament panels, built on [daikazu/bladewind](https://github.com/daikazu/bladewind).

A Filament panel theme is roughly 650 KB, and about 90% of it is the `fi-*` component rules. On a
Filament 4/5 (Tailwind 4) theme, BladeWind on its own only splits Tailwind's utilities per page and
keeps components in the shared root, so on a panel it saves almost nothing. This plugin splits the
components too, and keeps every page styled after Livewire updates:

- **Components are split per page.** Each page loads a small shared root plus the `fi-*`
  components and utilities it actually uses, and both layers keep their cascade position.
- **Livewire updates get their missing CSS.** Filament renders action modals, forms and
  notifications on demand. When an update's HTML needs rules the page didn't load, they're added
  to the response and put in `<head>` before Livewire morphs the DOM, so nothing paints unstyled.
  The rules are re-emitted in stylesheet order, so a streamed rule can't win the cascade out of
  order.
- **Classes added by Filament's JavaScript are always loaded.** Every `fi-*` class that appears as
  a string literal in Filament's published JS (added with `classList`) goes into every page.

Measured on a real panel (theme ~657 KB):

| Page | Loaded CSS | Saving |
|------|-----------:|-------:|
| Login | 181 KB | 72% |
| Dashboard | 259 KB | 61% |
| Create form | 288 KB | 56% |
| Resource table | 398 KB | 39% |

> [!WARNING]
> **Experimental.** This plugin builds on BladeWind internals (0.1.x, pinned to that minor) and on
> how Livewire and Filament ship updates. Test your panel's pages, modals and actions before you use
> it in production. The measurements above and the end-to-end modal test come from a Filament 5
> panel; the 1.x and 2.x branches are covered by unit tests only.

## Compatibility

| Branch | Filament | Livewire | Tailwind (theme) | Laravel | PHP | Package version |
|--------|----------|----------|------------------|---------|-----|-----------------|
| 1.x | 3.3 | 3.x | 3 | 13.x | 8.4+ | ^1.0 |
| 2.x | 4.x | 3.x | 4 | 13.x | 8.4+ | ^2.0 |
| 3.x | 5.x | 4.x | 4 | 13.x | 8.4+ | ^3.0 |

BladeWind requires Laravel 13 and PHP 8.4 on every branch.

- **Tailwind 4 themes (2.x, 3.x):** the driver moves `@layer components` into the per-page pool,
  and each page file re-opens the components and utilities layers.
- **Tailwind 3 themes (1.x):** the output has no cascade layers, so BladeWind's flat split already
  pools everything from the first class rule on, the `fi-*` components included.

## Installation

```bash
composer require jeffersongoncalves/filament-bladewind
```

## Usage

Replace `->viteTheme()` with the plugin, passing it the same entry:

```php
use JeffersonGoncalves\Filament\BladeWind\BladeWindPlugin;

$panel
    // ->viteTheme('resources/css/filament/admin/theme.css')
    ->plugins([
        BladeWindPlugin::make()
            ->theme('resources/css/filament/admin/theme.css'),
    ]);
```

Build your assets as usual (`npm run build` / `bun run build`). The web server needs write access
to `public/bladewind`, where BladeWind writes the page files. Add that directory to `.gitignore`.

### Options

```php
BladeWindPlugin::make()
    ->theme('resources/css/filament/admin/theme.css')
    // Views of third-party plugins to analyse (vendor/filament/* and resources/views are always in).
    ->paths([base_path('vendor/acme/filament-widgets/resources/views')])
    // Classes every page carries: exact names or `prefix-*` patterns.
    ->safelist(['fi-custom-*'])
    // How long (seconds) a loaded page's class set is kept for its Livewire updates.
    ->ttl(43200);
```

The public site of the same app can keep using BladeWind with its own stylesheet
(`bladewind.stylesheets` in `config/bladewind.php`). Panel requests switch that setting to the
panel theme for the duration of the request.

## How it works

1. **Theme.** The plugin sets the panel theme to BladeWind's `@bladewindStyles` links. A persistent
   panel middleware points BladeWind at the panel theme, the panel's views and `vendor/filament/*`,
   and at this package's `filament` CSS driver.
2. **Split.** The `fi-*` components go into the per-page pool next to the utilities. The support
   declarations (theme variables, `--tw-*` properties, `@property`, `@keyframes`) stay whole in the
   root, which is a few KB. Because the root has every variable, streamed rules never depend on
   one the page didn't load.
3. **Page load.** The middleware records the page's class set in the cache under a random id and
   puts the id in a `<meta>` tag.
4. **Livewire update.** A small script sends that id with every Livewire request. A Livewire
   `response` listener reads the HTML the update renders and computes the rules the page doesn't
   have yet. The HTML is the component HTML, plus the `partials` and `islands` that Livewire 4
   uses (Filament 5 sends action modals as a partial). The listener returns the rules in
   `effects.bladewind`, and the script adds them to `<head>` before the morph. On Livewire 4 that
   happens in `interceptMessage` → `onSuccess`; on Livewire 3 it happens in the
   `payload.intercept` hook.

If the cache has lost a page's id, the next update simply re-sends everything its fragment needs.
It never sends less.

## Diagnostics you will see

BladeWind reports these codes for a panel, and they're expected:

- **BW1001:** a view uses `@include($name)`. Filament renders components through dynamic
  includes; their HTML is covered when it renders.
- **BW2002:** an Alpine binding can't be enumerated.
- **BW2004:** a `@class` entry isn't a string.
- **BW6005:** the support layers are kept whole, which the Tailwind 4 driver does on purpose
  (2.x, 3.x).

## Testing your panel

BladeWind's `AssertsPageStyles` trait works on panel pages as well:

```php
use Daikazu\BladeWind\Testing\AssertsPageStyles;
use Daikazu\BladeWind\Testing\PageExpectation;

uses(AssertsPageStyles::class);

it('styles the users table', function () {
    $this->actingAs($admin, 'admin');

    $this->assertPageStyles('/admin/users', new PageExpectation(
        framework: 'filament',
        diagnostics: ['BW1001', 'BW2002', 'BW2004', 'BW6005'], // no BW6005 on 1.x
    ));
});
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [Jefferson Gonçalves](https://github.com/jeffersongoncalves)
- [Mike Wall](https://github.com/daikazu) for [BladeWind](https://github.com/daikazu/bladewind)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
