<?php

use Illuminate\Filesystem\Filesystem;
use JeffersonGoncalves\Filament\BladeWind\Css\Memo;
use JeffersonGoncalves\Filament\BladeWind\Css\RuleDelta;
use JeffersonGoncalves\Filament\BladeWind\Css\RuntimeTokens;

it('reuses a value until its version changes, then replaces it under the same key', function () {
    $calls = 0;
    $compute = function () use (&$calls): int {
        return ++$calls;
    };

    expect(Memo::get('thing', 'v1', $compute))->toBe(1)
        ->and(Memo::get('thing', 'v1', $compute))->toBe(1)
        ->and(Memo::get('thing', 'v2', $compute))->toBe(2)
        ->and(Memo::get('thing', 'v2', $compute))->toBe(2);
});

it('re-scans Filament JavaScript only when a script changes', function () {
    $dir = sys_get_temp_dir().'/filament-bladewind-'.bin2hex(random_bytes(4));
    mkdir($dir);
    file_put_contents($dir.'/a.js', 'x="fi-one"');

    $original = filemtime($dir.'/a.js');

    expect((new RuntimeTokens(new Filesystem, $dir))->all())->toBe(['fi-one']);

    // Same mtime: the cached scan is used, even though the content changed.
    file_put_contents($dir.'/a.js', 'x="fi-two"');
    touch($dir.'/a.js', $original);
    clearstatcache();
    $stale = (new RuntimeTokens(new Filesystem, $dir))->all();

    // Newer mtime (as after filament:upgrade): re-scanned.
    touch($dir.'/a.js', $original + 60);
    clearstatcache();
    $fresh = (new RuntimeTokens(new Filesystem, $dir))->all();

    (new Filesystem)->deleteDirectory($dir);

    expect($stale)->toBe(['fi-one'])
        ->and($fresh)->toBe(['fi-two']);
});

it('skips the rule index when an update renders no token the page lacks', function () {
    // Nothing in the container resolves a stylesheet here: reaching the index would still return
    // '' but the early exit must not need it at all.
    expect(app(RuleDelta::class)->css(['fi-a', 'fi-b'], ['fi-b', 'fi-a']))->toBe('');
});
