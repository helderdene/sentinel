<?php

use App\Enums\IncidentChannel;
use App\Enums\IncidentPriority;
use App\Enums\PersonnelCategory;
use App\Enums\RecognitionSeverity;
use App\Events\IncidentCreated;
use App\Events\RecognitionAlertReceived;
use App\Models\Camera;
use App\Models\Incident;
use App\Models\IncidentCategory;
use App\Models\IncidentType;
use App\Models\Personnel;
use App\Models\RecognitionEvent;
use App\Models\User;
use App\Services\FrasIncidentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

pest()->group('fras');

beforeEach(function () {
    Event::fake([IncidentCreated::class, RecognitionAlertReceived::class]);

    $category = IncidentCategory::firstOrCreate(
        ['name' => 'Crime / Security'],
        ['icon' => 'Shield', 'is_active' => true, 'sort_order' => 4]
    );

    IncidentType::updateOrCreate(
        ['code' => 'person_of_interest'],
        [
            'incident_category_id' => $category->id,
            'category' => 'Crime / Security',
            'name' => 'Person of Interest',
            'default_priority' => 'P2',
            'is_active' => true,
            'show_in_public_app' => false,
            'sort_order' => 999,
        ]
    );
});

/**
 * Build a RecognitionEvent wired to a Camera + Personnel for promote tests.
 * Manual promote bypasses severity/confidence/dedup gates, so fixtures can
 * use Warning severity + sub-threshold similarity to prove the bypass.
 */
function promoteEvent(
    PersonnelCategory $category = PersonnelCategory::Block,
    RecognitionSeverity $severity = RecognitionSeverity::Critical,
    float $similarity = 0.90,
): RecognitionEvent {
    $camera = Camera::factory()->create();
    $personnel = Personnel::factory()->create(['category' => $category]);

    return RecognitionEvent::factory()
        ->for($camera)
        ->for($personnel)
        ->create([
            'severity' => $severity,
            'similarity' => $similarity,
        ]);
}

it('promotes with operator-picked priority + reason', function () {
    $event = promoteEvent();
    $actor = User::factory()->create();

    $incident = app(FrasIncidentFactory::class)->createFromRecognitionManual(
        $event,
        IncidentPriority::P1,
        'Operator judgement override — suspect on active BOLO.',
        $actor,
    );

    expect($incident)->toBeInstanceOf(Incident::class);
    expect($incident->priority)->toBe(IncidentPriority::P1);
    expect($incident->channel)->toBe(IncidentChannel::IoT);
    expect($incident->incidentType->code)->toBe('person_of_interest');
});

it('bypasses severity gate — Warning severity still promotes', function () {
    // Warning-severity event would be blocked by automatic path; manual
    // promote must succeed to prove the operator override actually bypasses.
    $event = promoteEvent(PersonnelCategory::Block, RecognitionSeverity::Warning, 0.65);
    $actor = User::factory()->create();

    $incident = app(FrasIncidentFactory::class)->createFromRecognitionManual(
        $event,
        IncidentPriority::P2,
        'Override despite warning — dispatcher has additional context.',
        $actor,
    );

    expect($incident)->toBeInstanceOf(Incident::class);
    expect($incident->priority)->toBe(IncidentPriority::P2);
});

it('rejects promotion when personnel_id is null (422)', function () {
    $camera = Camera::factory()->create();
    $event = RecognitionEvent::factory()->for($camera)->create([
        'personnel_id' => null,
        'severity' => RecognitionSeverity::Warning,
    ]);
    $actor = User::factory()->create();

    expect(fn () => app(FrasIncidentFactory::class)->createFromRecognitionManual(
        $event,
        IncidentPriority::P2,
        'Trying to promote unmatched event.',
        $actor,
    ))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('rejects promotion for allow-category personnel (422)', function () {
    $event = promoteEvent(PersonnelCategory::Allow);
    $actor = User::factory()->create();

    expect(fn () => app(FrasIncidentFactory::class)->createFromRecognitionManual(
        $event,
        IncidentPriority::P2,
        'Trying to promote an allow-list match.',
        $actor,
    ))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('writes fras_operator_promote trigger + audit fields on the timeline entry', function () {
    $event = promoteEvent(PersonnelCategory::Missing);
    $actor = User::factory()->create();
    $reason = 'Dispatcher confirmed sighting with barangay captain.';

    $incident = app(FrasIncidentFactory::class)->createFromRecognitionManual(
        $event,
        IncidentPriority::P1,
        $reason,
        $actor,
    );

    $timeline = $incident->timeline()->where('event_type', 'incident_created')->first();
    expect($timeline)->not->toBeNull();

    $data = $timeline->event_data;
    expect($data['source'])->toBe('fras_recognition');
    expect($data['trigger'])->toBe('fras_operator_promote');
    expect($data['recognition_event_id'])->toBe($event->id);
    expect($data['promoted_by_user_id'])->toBe($actor->id);
    expect($data['promoted_priority'])->toBe(IncidentPriority::P1->value);
    expect($data['promotion_reason'])->toBe($reason);
    expect($data['personnel_id'])->toBe($event->personnel_id);
    expect($data['personnel_category'])->toBe(PersonnelCategory::Missing->value);
});

it('dispatches IncidentCreated and RecognitionAlertReceived', function () {
    $event = promoteEvent();
    $actor = User::factory()->create();

    $incident = app(FrasIncidentFactory::class)->createFromRecognitionManual(
        $event,
        IncidentPriority::P2,
        'Standard promote.',
        $actor,
    );

    Event::assertDispatched(IncidentCreated::class);
    Event::assertDispatched(RecognitionAlertReceived::class, function ($e) use ($incident) {
        return $e->incident?->id === $incident->id;
    });
});

it('appends actor attribution + reason to the incident notes field', function () {
    $event = promoteEvent(PersonnelCategory::Block);
    $actor = User::factory()->create(['name' => 'Dispatcher Rosa']);
    $reason = 'Face confirmed by unit lead on-scene.';

    $incident = app(FrasIncidentFactory::class)->createFromRecognitionManual(
        $event,
        IncidentPriority::P2,
        $reason,
        $actor,
    );

    expect($incident->notes)->toContain(' — Manually promoted by Dispatcher Rosa: Face confirmed by unit lead on-scene.');
});

it('links the promoted event to the new incident', function () {
    $event = promoteEvent();
    $actor = User::factory()->create();

    $incident = app(FrasIncidentFactory::class)->createFromRecognitionManual(
        $event,
        IncidentPriority::P2,
        'Link check.',
        $actor,
    );

    expect($event->fresh()->incident_id)->toBe($incident->id);
});
