<?php

use Illuminate\Support\Facades\Artisan;

pest()->group('fras');

afterEach(function () {
    $dir = storage_path('app/dpa-exports/' . now()->format('Y-m-d'));
    if (is_dir($dir)) {
        $files = glob($dir . '/*.pdf');
        if ($files !== false) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        @rmdir($dir);
    }
});

it('exports the PIA doc to PDF with --doc=pia --lang=en', function () {
    $exit = Artisan::call('fras:dpa:export', ['--doc' => 'pia', '--lang' => 'en']);

    expect($exit)->toBe(0);

    $expected = storage_path('app/dpa-exports/' . now()->format('Y-m-d') . '/pia-en.pdf');
    expect(file_exists($expected))->toBeTrue();
    expect(filesize($expected))->toBeGreaterThan(0);
});

it('exports all 3 docs with --doc=all --lang=en', function () {
    Artisan::call('fras:dpa:export', ['--doc' => 'all', '--lang' => 'en']);

    $dir = storage_path('app/dpa-exports/' . now()->format('Y-m-d'));

    expect(file_exists($dir . '/pia-en.pdf'))->toBeTrue();
    expect(file_exists($dir . '/signage-en.pdf'))->toBeTrue();
    expect(file_exists($dir . '/training-en.pdf'))->toBeTrue();
});

it('exports the Filipino signage with --doc=signage --lang=tl', function () {
    Artisan::call('fras:dpa:export', ['--doc' => 'signage', '--lang' => 'tl']);

    $expected = storage_path('app/dpa-exports/' . now()->format('Y-m-d') . '/signage-tl.pdf');

    expect(file_exists($expected))->toBeTrue();
});

it('prints the absolute output path to stdout', function () {
    Artisan::call('fras:dpa:export', ['--doc' => 'pia', '--lang' => 'en']);

    $output = Artisan::output();
    $expected = storage_path('app/dpa-exports/' . now()->format('Y-m-d') . '/pia-en.pdf');

    expect($output)->toContain($expected);
});
