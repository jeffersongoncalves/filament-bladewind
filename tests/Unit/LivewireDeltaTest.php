<?php

use JeffersonGoncalves\Filament\BladeWind\LivewireDelta;

it('reads the component html and the partials Filament 5 sends its action modals in', function () {
    $markup = LivewireDelta::markup([
        'html' => '<div class="fi-page"></div>',
        'partials' => ['action-modals' => '<div class="fi-modal"></div>'],
        'islands' => [['html' => '<span class="fi-badge"></span>']],
        'returns' => [null],
        'dispatches' => [['name' => 'sync-action-modals', 'params' => ['id' => 'abc']]],
    ]);

    expect($markup)->toBe('<div class="fi-page"></div><div class="fi-modal"></div><span class="fi-badge"></span>');
});

it('is empty for an update that rendered nothing', function () {
    expect(LivewireDelta::markup(['returns' => [null]]))->toBe('')
        ->and(LivewireDelta::markup(null))->toBe('');
});
