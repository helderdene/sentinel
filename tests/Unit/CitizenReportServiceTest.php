<?php

use App\Models\Incident;

it('generates an 8-character tracking token', function () {
    $token = Incident::generateTrackingToken();

    expect($token)->toHaveLength(8);
});

it('generates tokens using only the allowed 30-char alphabet', function () {
    $allowedChars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    for ($i = 0; $i < 10; $i++) {
        $token = Incident::generateTrackingToken();

        for ($j = 0; $j < strlen($token); $j++) {
            expect(str_contains($allowedChars, $token[$j]))->toBeTrue(
                "Character '{$token[$j]}' at position {$j} is not in the allowed alphabet"
            );
        }
    }
});

it('does not include ambiguous characters O, I, L, 0, 1', function () {
    $forbidden = ['O', 'I', 'L', '0', '1'];

    for ($i = 0; $i < 20; $i++) {
        $token = Incident::generateTrackingToken();

        foreach ($forbidden as $char) {
            expect(str_contains($token, $char))->toBeFalse(
                "Token '{$token}' contains forbidden character '{$char}'"
            );
        }
    }
});
