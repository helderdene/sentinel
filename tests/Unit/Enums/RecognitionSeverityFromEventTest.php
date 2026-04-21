<?php

use App\Enums\RecognitionSeverity;

pest()->group('fras');

it('classifies block-list (personType=1) as Critical regardless of verifyStatus', function (int $personType, int $verifyStatus, RecognitionSeverity $expected) {
    expect(RecognitionSeverity::fromEvent($personType, $verifyStatus))->toBe($expected);
})->with([
    'block-list + nothing' => [1, 0, RecognitionSeverity::Critical],
    'block-list + allow-list hit' => [1, 1, RecognitionSeverity::Critical],
]);

it('classifies refused (verifyStatus=2) as Warning when not block-listed', function () {
    expect(RecognitionSeverity::fromEvent(2, 2))->toBe(RecognitionSeverity::Warning);
});

it('collapses FRAS Ignored (verifyStatus in {0,3}) to Info per IRMS enum parity', function (int $personType, int $verifyStatus, RecognitionSeverity $expected) {
    expect(RecognitionSeverity::fromEvent($personType, $verifyStatus))->toBe($expected);
})->with([
    'stranger / nothing' => [0, 0, RecognitionSeverity::Info],
    'stranger / unknown' => [0, 3, RecognitionSeverity::Info],
]);

it('classifies allow-list match (personType=0, verifyStatus=1) as Info', function () {
    expect(RecognitionSeverity::fromEvent(0, 1))->toBe(RecognitionSeverity::Info);
});
