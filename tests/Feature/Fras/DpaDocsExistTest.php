<?php

pest()->group('fras');

it('ships the PIA template markdown doc', function () {
    $path = base_path('docs/dpa/PIA-template.md');

    expect(file_exists($path))->toBeTrue();

    $content = file_get_contents($path);

    expect($content)->toContain('Privacy Impact Assessment');
});

it('PIA template contains all 10 H2 sections', function () {
    $path = base_path('docs/dpa/PIA-template.md');
    $content = file_get_contents($path);

    preg_match_all('/^## /m', $content, $matches);

    expect(count($matches[0]))->toBeGreaterThanOrEqual(10);
});

it('ships English signage template with 4 merge fields', function () {
    $path = base_path('docs/dpa/signage-template.md');

    expect(file_exists($path))->toBeTrue();

    $content = file_get_contents($path);

    expect($content)->toContain('{CAMERA_LOCATION}');
    expect($content)->toContain('{CONTACT_DPO}');
    expect($content)->toContain('{CONTACT_OFFICE}');
    expect($content)->toContain('{RETENTION_WINDOW}');
});

it('ships Filipino signage template with the same 4 merge fields', function () {
    $path = base_path('docs/dpa/signage-template.tl.md');

    expect(file_exists($path))->toBeTrue();

    $content = file_get_contents($path);

    expect($content)->toContain('{CAMERA_LOCATION}');
    expect($content)->toContain('{CONTACT_DPO}');
    expect($content)->toContain('{CONTACT_OFFICE}');
    expect($content)->toContain('{RETENTION_WINDOW}');
});

it('ships the operator training doc with DPA role matrix', function () {
    $path = base_path('docs/dpa/operator-training.md');

    expect(file_exists($path))->toBeTrue();

    $content = file_get_contents($path);

    expect($content)->toContain('Operator Training');
    expect($content)->toContain('Role Matrix');
});

it('ships the shared dompdf Blade template', function () {
    $path = base_path('resources/views/dpa/export.blade.php');

    expect(file_exists($path))->toBeTrue();

    $content = file_get_contents($path);

    expect($content)->toContain('DejaVu Sans');
});
