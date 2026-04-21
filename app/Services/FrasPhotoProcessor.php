<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\PhotoTooLargeException;
use App\Models\Personnel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Port of FRAS PhotoProcessor adapted for IRMS (phase 20, D-18/D-19):
 *   - uses the private `fras_photos` disk (config/filesystems.php)
 *   - reads sizing knobs from `config('fras.photo.*')` (config/fras.php)
 *   - stores as `personnel/{personnel->id}.jpg` using the Personnel UUID
 *   - throws PhotoTooLargeException when the degradation loop bottoms out
 */
final class FrasPhotoProcessor
{
    /**
     * Process an uploaded photo: orient, resize, compress to target bytes, hash, store.
     *
     * @return array{photo_path: string, photo_hash: string}
     */
    public function process(UploadedFile $file, Personnel $personnel): array
    {
        $maxDim = (int) config('fras.photo.max_dimension', 1080);
        $quality = (int) config('fras.photo.jpeg_quality', 85);
        $maxBytes = (int) config('fras.photo.max_size_bytes', 1_048_576);

        $image = Image::read($file->getRealPath());
        $image->orient();
        $image->scaleDown(width: $maxDim, height: $maxDim);

        $encoded = $image->encode(new JpegEncoder(quality: $quality));

        while (strlen((string) $encoded) > $maxBytes && $quality > 40) {
            $quality -= 10;
            $encoded = $image->encode(new JpegEncoder(quality: $quality));
        }

        if (strlen((string) $encoded) > $maxBytes) {
            throw new PhotoTooLargeException(
                'Photo could not be compressed below '.$maxBytes.' bytes at quality=40.'
            );
        }

        $path = "personnel/{$personnel->id}.jpg";
        Storage::disk('fras_photos')->put($path, (string) $encoded);

        return [
            'photo_path' => $path,
            'photo_hash' => md5((string) $encoded),
        ];
    }

    /**
     * Delete a photo from the fras_photos disk. Idempotent + null-safe.
     */
    public function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        Storage::disk('fras_photos')->delete($path);
    }
}
