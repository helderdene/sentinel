<?php

use App\Enums\UserRole;
use App\Models\Personnel;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

pest()->group('fras');

beforeEach(function () {
    Storage::fake('fras_photos');
    $this->personnel = Personnel::factory()->create([
        'photo_path' => 'personnel/abc.jpg',
        'photo_hash' => 'hash1',
    ]);
    Storage::disk('fras_photos')->put(
        "personnel/{$this->personnel->id}.jpg",
        'fake-jpeg-bytes'
    );
});

function signedPhotoUrl(Personnel $personnel, int $minutes = 5): string
{
    return URL::temporarySignedRoute(
        'admin.personnel.photo',
        now()->addMinutes($minutes),
        ['personnel' => $personnel->id],
    );
}

it('streams the photo for a valid signed URL within 5 minutes for an operator', function () {
    $operator = User::factory()->create(['role' => UserRole::Operator]);
    $url = signedPhotoUrl($this->personnel);

    $this->actingAs($operator)
        ->get($url)
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');
});

it('returns 403 when the signed URL is expired (past 5-min TTL)', function () {
    $operator = User::factory()->create(['role' => UserRole::Operator]);
    $url = signedPhotoUrl($this->personnel, 5);

    // Fast-forward past the TTL
    Carbon::setTestNow(now()->addMinutes(6));

    $this->actingAs($operator)
        ->get($url)
        ->assertForbidden();

    Carbon::setTestNow();
});

it('returns 403 when the signature is tampered', function () {
    $operator = User::factory()->create(['role' => UserRole::Operator]);
    $url = signedPhotoUrl($this->personnel);

    // Tamper the signature query param
    $tampered = preg_replace('/signature=[^&]+/', 'signature=deadbeef', $url);

    $this->actingAs($operator)
        ->get($tampered)
        ->assertForbidden();
});

it('allows operator, supervisor, and admin roles (D-22 gate)', function () {
    foreach ([UserRole::Operator, UserRole::Supervisor, UserRole::Admin] as $role) {
        $user = User::factory()->create(['role' => $role]);
        $url = signedPhotoUrl($this->personnel);

        $this->actingAs($user)
            ->get($url)
            ->assertOk();
    }
});

it('denies responder and dispatcher roles (D-22 gate)', function () {
    foreach ([UserRole::Responder, UserRole::Dispatcher] as $role) {
        $user = User::factory()->create(['role' => $role]);
        $url = signedPhotoUrl($this->personnel);

        $this->actingAs($user)
            ->get($url)
            ->assertForbidden();
    }
});
