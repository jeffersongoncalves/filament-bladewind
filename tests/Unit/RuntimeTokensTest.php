<?php

use Illuminate\Filesystem\Filesystem;
use JeffersonGoncalves\Filament\BladeWind\Css\RuntimeTokens;

it('collects fi-* classes from string literals in Filament JavaScript', function () {
    $dir = sys_get_temp_dir().'/filament-bladewind-'.bin2hex(random_bytes(4));
    mkdir($dir.'/components', 0777, true);
    file_put_contents($dir.'/components/modal.js', 'el.classList.add("fi-modal-open");x=`fi-dropdown-panel fi-open`;y=\'not-fi-x\';z=fi-var');
    file_put_contents($dir.'/readme.txt', '"fi-ignored"');

    $tokens = (new RuntimeTokens(new Filesystem, $dir))->all();

    (new Filesystem)->deleteDirectory($dir);

    expect($tokens)->toBe(['fi-dropdown-panel', 'fi-modal-open', 'fi-open']);
});

it('is empty when Filament assets are not published', function () {
    expect((new RuntimeTokens(new Filesystem, '/does/not/exist'))->all())->toBe([]);
});
