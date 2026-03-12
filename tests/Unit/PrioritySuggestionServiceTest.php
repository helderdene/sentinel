<?php

use App\Services\PrioritySuggestionService;

it('returns default priority with base confidence when no keywords in notes', function () {
    $service = new PrioritySuggestionService;
    $result = $service->suggest('P3', '');

    expect($result)->toHaveKeys(['priority', 'confidence'])
        ->and($result['priority'])->toBe('P3')
        ->and($result['confidence'])->toBe(70);
});

it('escalates priority when escalation keywords exceed threshold', function () {
    $service = new PrioritySuggestionService;
    $result = $service->suggest('P3', 'there are multiple children trapped and unconscious');

    expect($result['priority'])->toBe('P2')
        ->and($result['confidence'])->toBeGreaterThan(70);
});

it('escalates priority multiple levels with extreme keywords', function () {
    $service = new PrioritySuggestionService;
    $result = $service->suggest('P3', 'multiple children trapped unconscious critical dying mass explosion');

    expect($result['priority'])->toBe('P1')
        ->and($result['confidence'])->toBe(99);
});

it('de-escalates priority when de-escalation keywords exceed threshold', function () {
    $service = new PrioritySuggestionService;
    $result = $service->suggest('P2', 'minor incident small contained stable situation');

    expect($result['priority'])->toBe('P3')
        ->and($result['confidence'])->toBeLessThan(70);
});

it('handles Filipino escalation keywords', function () {
    $service = new PrioritySuggestionService;
    $result = $service->suggest('P3', 'marami ang nakulong at walang malay ang bata');

    expect($result['priority'])->toBe('P2')
        ->and($result['confidence'])->toBeGreaterThan(70);
});

it('handles Filipino de-escalation keywords', function () {
    $service = new PrioritySuggestionService;
    $result = $service->suggest('P2', 'maliit na insidente kontrolado na kaunti lang');

    expect($result['priority'])->toBe('P3')
        ->and($result['confidence'])->toBeLessThan(70);
});

it('clamps confidence to 30-99 range', function () {
    $service = new PrioritySuggestionService;

    $highResult = $service->suggest('P1', 'trapped unconscious multiple children critical dying severe mass collapse explosion');
    expect($highResult['confidence'])->toBeLessThanOrEqual(99);

    $lowResult = $service->suggest('P4', 'minor small contained stable false drill test cancel');
    expect($lowResult['confidence'])->toBeGreaterThanOrEqual(30);
});

it('does not escalate beyond P1', function () {
    $service = new PrioritySuggestionService;
    $result = $service->suggest('P1', 'trapped unconscious multiple children critical dying');

    expect($result['priority'])->toBe('P1');
});

it('does not de-escalate beyond P4', function () {
    $service = new PrioritySuggestionService;
    $result = $service->suggest('P4', 'minor small contained stable false drill test cancel');

    expect($result['priority'])->toBe('P4');
});

it('returns base confidence with null notes', function () {
    $service = new PrioritySuggestionService;
    $result = $service->suggest('P2', '');

    expect($result['priority'])->toBe('P2')
        ->and($result['confidence'])->toBe(70);
});
