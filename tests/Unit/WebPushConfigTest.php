<?php

it('has VAPID public key configured', function () {
    expect(config('webpush.vapid.public_key'))->not->toBeEmpty();
});

it('has VAPID private key configured', function () {
    expect(config('webpush.vapid.private_key'))->not->toBeEmpty();
});

it('has VAPID subject configured', function () {
    expect(config('webpush.vapid.subject'))->not->toBeEmpty();
});
