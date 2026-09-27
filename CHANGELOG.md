# Changelog

All notable changes to `filament-bladewind` will be documented in this file.

## 1.0.0 - 2026-09-27

First release for **Filament 3.3** (Livewire 3, Tailwind 3 themes, Laravel 13, PHP 8.4).

Per-page CSS for Filament panels on top of [daikazu/bladewind](https://github.com/daikazu/bladewind):

- Each page loads a shared root plus only the `fi-*` components and utilities it uses (BladeWind's flat split of the Tailwind 3 theme).
- Livewire updates carry the rules their HTML needs and the page lacks, re-emitted in stylesheet order so the cascade holds, added to `<head>` before the morph (`payload.intercept`).
- `fi-*` classes set by Filament's JavaScript are carried by every page.

Experimental: builds on BladeWind 0.1.x internals (pinned to that minor).
