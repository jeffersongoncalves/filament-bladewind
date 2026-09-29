# Changelog

All notable changes to `filament-bladewind` will be documented in this file.

## 1.0.2 - 2026-09-29

Performance: cache the theme split, the rule index and the runtime-token scan.

BladeWind memoises its stylesheet work per PHP process, i.e. per request under PHP-FPM. For a Filament theme that meant ~120 ms to split it on every panel page, ~270 ms to build the rule index on every Livewire update that rendered HTML, and ~20 ms to scan Filament's published JavaScript on every page.

They now live in your cache store under one key each, replaced when their source changes (the theme's content hash for the split and index, the scripts' modification times for the scan): about 4.5 / 10 / 5 ms warm. An update that renders no class the page lacks returns before touching the index.

## 1.0.1 - 2026-09-27

Fixes the cascade for elements outside a Livewire update.

A streamed delta re-emitted the fragment's already-delivered rules after the page, but those rules also match elements the update never rendered: `.fi-icon-btn{display:flex}` jumped ahead of the topbar rule that hides the mobile close button on desktop, so an **X** showed next to the collapse arrow after a lazy component (database notifications) loaded or a modal opened.

The delta is now the stylesheet-ordered tail from the first new rule: the new rules plus every rule the page already has after that point, so any two rules keep their relative order.

## 1.0.0 - 2026-09-27

First release for **Filament 3.3** (Livewire 3, Tailwind 3 themes, Laravel 13, PHP 8.4).

Per-page CSS for Filament panels on top of [daikazu/bladewind](https://github.com/daikazu/bladewind):

- Each page loads a shared root plus only the `fi-*` components and utilities it uses (BladeWind's flat split of the Tailwind 3 theme).
- Livewire updates carry the rules their HTML needs and the page lacks, re-emitted in stylesheet order so the cascade holds, added to `<head>` before the morph (`payload.intercept`).
- `fi-*` classes set by Filament's JavaScript are carried by every page.

Experimental: builds on BladeWind 0.1.x internals (pinned to that minor).
