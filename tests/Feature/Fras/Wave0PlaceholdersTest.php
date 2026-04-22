<?php

/*
 * Phase 22 Wave 0 placeholders (per 22-VALIDATION §Wave 0 Requirements).
 *
 * Each skipped `it()` below registers a planned Phase 22 feature/browser test
 * file so Nyquist sampling has a name to target during later waves. The real
 * tests land in the plan noted in each skip() reason. Skipping (not pending)
 * keeps `php artisan test --group=fras` green while the scaffolds exist.
 */

pest()->group('fras');

it('Wave 0 placeholder — AlertFeedBroadcastTest', fn () => expect(true)->toBeTrue())
    ->skip('Wave 0 stub — implementation lands in Plan 22-03');

it('Wave 0 placeholder — AlertAckDismissTest', fn () => expect(true)->toBeTrue())
    ->skip('Wave 0 stub — implementation lands in Plan 22-06');

it('Wave 0 placeholder — EventHistoryFilterTest', fn () => expect(true)->toBeTrue())
    ->skip('Wave 0 stub — implementation lands in Plan 22-07');

it('Wave 0 placeholder — EventPromoteToIncidentTest', fn () => expect(true)->toBeTrue())
    ->skip('Wave 0 stub — implementation lands in Plan 22-07');

it('Wave 0 placeholder — ResponderSceneTabTest', fn () => expect(true)->toBeTrue())
    ->skip('Wave 0 stub — implementation lands in Plan 22-08');

it('Wave 0 placeholder — SignedUrlSceneImageTest', fn () => expect(true)->toBeTrue())
    ->skip('Wave 0 stub — implementation lands in Plan 22-03');

it('Wave 0 placeholder — FrasAccessLogTest', fn () => expect(true)->toBeTrue())
    ->skip('Wave 0 stub — implementation lands in Plan 22-03');

it('Wave 0 placeholder — RetentionPurgeTest', fn () => expect(true)->toBeTrue())
    ->skip('Wave 0 stub — implementation lands in Plan 22-04');

it('Wave 0 placeholder — PrivacyNoticeTest', fn () => expect(true)->toBeTrue())
    ->skip('Wave 0 stub — implementation lands in Plan 22-08');

it('Wave 0 placeholder — LegalSignoffTest', fn () => expect(true)->toBeTrue())
    ->skip('Wave 0 stub — implementation lands in Plan 22-09');

it('Wave 0 placeholder — FrasAlertsFeed (browser)', fn () => expect(true)->toBeTrue())
    ->skip('Wave 0 stub — implementation lands in Plan 22-06');

it('Wave 0 placeholder — FrasEventHistory (browser)', fn () => expect(true)->toBeTrue())
    ->skip('Wave 0 stub — implementation lands in Plan 22-07');
