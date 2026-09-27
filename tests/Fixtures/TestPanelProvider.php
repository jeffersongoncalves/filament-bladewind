<?php

namespace JeffersonGoncalves\Filament\BladeWind\Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;
use JeffersonGoncalves\Filament\BladeWind\BladeWindPlugin;

class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugins([
                BladeWindPlugin::make()
                    ->theme('resources/css/filament/admin/theme.css')
                    ->safelist(['fi-custom-*']),
            ]);
    }
}
