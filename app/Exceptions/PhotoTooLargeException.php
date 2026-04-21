<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by FrasPhotoProcessor when the degradation loop cannot compress
 * an uploaded photo below `config('fras.photo.max_size_bytes')` even at
 * the minimum quality threshold (40).
 */
class PhotoTooLargeException extends RuntimeException {}
