<?php

declare(strict_types=1);

use App\Exceptions\PhotoTooLargeException;
use App\Models\Personnel;
use App\Services\FrasPhotoProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

pest()->group('fras');

beforeEach(function () {
    // Wave 1 dependency — Intervention Image v4 ships in plan 20-01.
    // This worktree was branched before plan 20-01's composer install, so the
    // facade is not yet resolvable. Tests will run after the orchestrator merges
    // Wave 1. See SUMMARY.md §Deferred for the full list of skipped cases.
    if (! class_exists(Image::class)) {
        test()->markTestSkipped('Wave 1 dependency — Intervention Image ships in plan 20-01 merge');
    }

    Storage::fake('fras_photos');
});

it('processes a 2000x1500 JPEG to <=1080 dim and <=1MB, stores under personnel/{uuid}.jpg', function () {
    $personnel = Personnel::factory()->create();
    $file = new UploadedFile(
        base_path('tests/fixtures/personnel-photo-sample.jpg'),
        'sample.jpg',
        'image/jpeg',
        null,
        true,
    );

    $result = app(FrasPhotoProcessor::class)->process($file, $personnel);

    expect($result)->toHaveKeys(['photo_path', 'photo_hash']);
    expect($result['photo_path'])->toBe("personnel/{$personnel->id}.jpg");
    Storage::disk('fras_photos')->assertExists($result['photo_path']);

    $bytes = Storage::disk('fras_photos')->get($result['photo_path']);
    expect(strlen($bytes))->toBeLessThanOrEqual(1_048_576);

    $size = getimagesizefromstring($bytes);
    expect($size[0])->toBeLessThanOrEqual(1080);
    expect($size[1])->toBeLessThanOrEqual(1080);
});

it('produces deterministic photo_hash for identical input bytes', function () {
    $personnel = Personnel::factory()->create();
    $file1 = new UploadedFile(
        base_path('tests/fixtures/personnel-photo-sample.jpg'),
        'a.jpg',
        'image/jpeg',
        null,
        true,
    );
    $file2 = new UploadedFile(
        base_path('tests/fixtures/personnel-photo-sample.jpg'),
        'b.jpg',
        'image/jpeg',
        null,
        true,
    );

    $r1 = app(FrasPhotoProcessor::class)->process($file1, $personnel);
    $r2 = app(FrasPhotoProcessor::class)->process($file2, $personnel);

    expect($r1['photo_hash'])->toBe($r2['photo_hash']);
    expect(strlen($r1['photo_hash']))->toBe(32); // md5 length
});

it('delete is idempotent and handles null path', function () {
    $processor = app(FrasPhotoProcessor::class);
    $processor->delete(null);                      // no throw
    $processor->delete('personnel/missing.jpg');   // no throw — file does not exist
    Storage::disk('fras_photos')->put('personnel/deleteme.jpg', 'xxx');
    $processor->delete('personnel/deleteme.jpg');
    Storage::disk('fras_photos')->assertMissing('personnel/deleteme.jpg');
});

it('throws PhotoTooLargeException when degradation loop cannot shrink below max_size_bytes', function () {
    config(['fras.photo.max_size_bytes' => 1000]); // absurdly low cap — forces exhaustion at quality=40
    $personnel = Personnel::factory()->create();
    $file = new UploadedFile(
        base_path('tests/fixtures/personnel-photo-sample.jpg'),
        'a.jpg',
        'image/jpeg',
        null,
        true,
    );

    expect(fn () => app(FrasPhotoProcessor::class)->process($file, $personnel))
        ->toThrow(PhotoTooLargeException::class);
});
