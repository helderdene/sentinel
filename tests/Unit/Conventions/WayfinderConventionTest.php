<?php

use Symfony\Component\Finder\Finder;

/*
|--------------------------------------------------------------------------
| Wayfinder Convention Guard
|--------------------------------------------------------------------------
|
| This test scans `resources/js/**` (excluding Wayfinder-generated
| directories) for hardcoded route literals that should be using
| the typed-route actions from `resources/js/actions/`.
|
| When this test fails, replace the literal URL in the offending file
| with the appropriate Wayfinder action. See .claude/skills/wayfinder-development/SKILL.md.
*/

function jsSourceFiles(): Finder
{
    return (new Finder)
        ->in(base_path('resources/js'))
        ->exclude(['actions', 'routes', 'wayfinder'])
        ->name(['*.ts', '*.vue'])
        ->notName('sw.ts')
        ->files();
}

it('forbids literal /intake/{id}/override-priority and /intake/{id}/recall URLs in Vue/TS sources', function () {
    $violations = [];
    $pattern = '#/intake/[^\'"`\s]+/(override-priority|recall)#';

    foreach (jsSourceFiles() as $file) {
        $contents = $file->getContents();

        if (preg_match($pattern, $contents)) {
            $violations[] = $file->getRelativePathname();
        }
    }

    expect($violations)->toBeEmpty(
        "Banned literal intake URLs (/intake/{id}/override-priority or /intake/{id}/recall) found in:\n  - "
        .implode("\n  - ", $violations)
        ."\n\nUse Wayfinder actions instead:\n"
        ."  import { overridePriority, recall } from '@/actions/App/Http/Controllers/IntakeStationController';\n"
        ."  router.post(overridePriority(id).url, { priority }, options);\n"
        .'  router.post(recall(id).url, {}, options);'
    );
});

it('forbids literal /push-subscriptions URL in Vue/TS sources', function () {
    $violations = [];
    $pattern = "#['\"`]/push-subscriptions['\"`]#";

    foreach (jsSourceFiles() as $file) {
        $contents = $file->getContents();

        if (preg_match($pattern, $contents)) {
            $violations[] = $file->getRelativePathname();
        }
    }

    expect($violations)->toBeEmpty(
        "Banned literal '/push-subscriptions' URL found in:\n  - "
        .implode("\n  - ", $violations)
        ."\n\nUse Wayfinder actions instead:\n"
        ."  import { store, destroy } from '@/actions/App/Http/Controllers/PushSubscriptionController';\n"
        ."  await fetch(store.url(), { method: 'POST', ... });\n"
        ."  await fetch(destroy.url(), { method: 'DELETE', ... });"
    );
});
