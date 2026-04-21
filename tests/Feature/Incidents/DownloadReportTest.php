<?php

use App\Enums\IncidentStatus;
use App\Enums\UserRole;
use App\Events\IncidentCreated;
use App\Models\Incident;
use App\Models\IncidentType;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Event::fake([IncidentCreated::class]);
    Storage::fake('local');
});

function makeResolvedIncidentWithReport(): Incident
{
    $type = IncidentType::factory()->create();
    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Resolved,
        'incident_type_id' => $type->id,
        'resolved_at' => now(),
    ]);
    $path = "incident-reports/{$incident->incident_no}.pdf";
    Storage::disk('local')->put($path, '%PDF-1.4 fake pdf bytes');
    $incident->update(['report_pdf_url' => $path]);

    return $incident->fresh();
}

function makeResolvedIncidentWithMissingFile(): Incident
{
    $type = IncidentType::factory()->create();
    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Resolved,
        'incident_type_id' => $type->id,
        'resolved_at' => now(),
    ]);
    $incident->update(['report_pdf_url' => 'incident-reports/missing.pdf']);

    return $incident->fresh();
}

it('dispatcher can download the PDF', function () {
    $incident = makeResolvedIncidentWithReport();
    $user = User::factory()->create(['role' => UserRole::Dispatcher]);

    $this->actingAs($user)
        ->get(route('incidents.download-report', $incident))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');
});

it('operator can download the PDF', function () {
    $incident = makeResolvedIncidentWithReport();
    $user = User::factory()->create(['role' => UserRole::Operator]);

    $this->actingAs($user)
        ->get(route('incidents.download-report', $incident))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');
});

it('supervisor can download the PDF', function () {
    $incident = makeResolvedIncidentWithReport();
    $user = User::factory()->create(['role' => UserRole::Supervisor]);

    $this->actingAs($user)
        ->get(route('incidents.download-report', $incident))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');
});

it('admin can download the PDF', function () {
    $incident = makeResolvedIncidentWithReport();
    $user = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($user)
        ->get(route('incidents.download-report', $incident))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');
});

it('assigned responder can download post-resolution (even with unassigned_at set)', function () {
    $incident = makeResolvedIncidentWithReport();
    $unit = Unit::factory()->create();
    $dispatcher = User::factory()->create(['role' => UserRole::Dispatcher]);
    $incident->assignedUnits()->attach($unit->id, [
        'assigned_at' => now()->subHour(),
        'acknowledged_at' => now()->subMinutes(55),
        'unassigned_at' => now()->subMinutes(5),
        'assigned_by' => $dispatcher->id,
    ]);
    $user = User::factory()->create([
        'role' => UserRole::Responder,
        'unit_id' => $unit->id,
    ]);

    $this->actingAs($user)
        ->get(route('incidents.download-report', $incident))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');
});

it('unrelated responder receives 403', function () {
    $incident = makeResolvedIncidentWithReport();
    $attachedUnit = Unit::factory()->create();
    $dispatcher = User::factory()->create(['role' => UserRole::Dispatcher]);
    $incident->assignedUnits()->attach($attachedUnit->id, [
        'assigned_at' => now(),
        'assigned_by' => $dispatcher->id,
    ]);
    $otherUnit = Unit::factory()->create();
    $user = User::factory()->create([
        'role' => UserRole::Responder,
        'unit_id' => $otherUnit->id,
    ]);

    $this->actingAs($user)
        ->get(route('incidents.download-report', $incident))
        ->assertForbidden();
});

it('responder with no unit assigned receives 403', function () {
    $incident = makeResolvedIncidentWithReport();
    $user = User::factory()->create([
        'role' => UserRole::Responder,
        'unit_id' => null,
    ]);

    $this->actingAs($user)
        ->get(route('incidents.download-report', $incident))
        ->assertForbidden();
});

it('returns 404 when report_pdf_url is null', function () {
    $type = IncidentType::factory()->create();
    $incident = Incident::factory()->create([
        'status' => IncidentStatus::Resolved,
        'incident_type_id' => $type->id,
        'resolved_at' => now(),
        'report_pdf_url' => null,
    ]);
    $user = User::factory()->create(['role' => UserRole::Dispatcher]);

    $this->actingAs($user)
        ->get(route('incidents.download-report', $incident))
        ->assertNotFound();
});

it('returns 404 when file is missing from storage', function () {
    $incident = makeResolvedIncidentWithMissingFile();
    $user = User::factory()->create(['role' => UserRole::Dispatcher]);

    $this->actingAs($user)
        ->get(route('incidents.download-report', $incident))
        ->assertNotFound();
});

it('redirects unauthenticated requests to login', function () {
    $incident = makeResolvedIncidentWithReport();

    $this->get(route('incidents.download-report', $incident))
        ->assertRedirect(route('login'));
});
