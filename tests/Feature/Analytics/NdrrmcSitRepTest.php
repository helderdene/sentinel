<?php

use App\Contracts\NdrrmcReportServiceInterface;
use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Events\IncidentCreated;
use App\Events\IncidentStatusChanged;
use App\Events\UnitStatusChanged;
use App\Jobs\GenerateIncidentReport;
use App\Jobs\GenerateNdrrmcSitRep;
use App\Listeners\GenerateReportsOnIncidentResolution;
use App\Models\GeneratedReport;
use App\Models\Incident;
use App\Models\IncidentType;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Event::fake([
        IncidentCreated::class,
        IncidentStatusChanged::class,
        UnitStatusChanged::class,
    ]);
    Storage::fake('local');
});

it('creates a GeneratedReport record with type ndrrmc_sitrep', function () {
    $type = IncidentType::factory()->create();

    $incident = Incident::factory()->create([
        'incident_type_id' => $type->id,
        'priority' => IncidentPriority::P1,
        'status' => IncidentStatus::Resolved,
        'outcome' => 'TREATED_ON_SCENE',
        'resolved_at' => now(),
    ]);

    $job = new GenerateNdrrmcSitRep($incident);
    app()->call([$job, 'handle']);

    $report = GeneratedReport::where('type', 'ndrrmc_sitrep')
        ->where('period', $incident->incident_no)
        ->first();

    expect($report)->not->toBeNull();
    expect($report->status)->toBe('ready');
    expect($report->title)->toContain('NDRRMC SitRep');
});

it('generates a SitRep PDF file in storage', function () {
    $type = IncidentType::factory()->create();

    $incident = Incident::factory()->create([
        'incident_type_id' => $type->id,
        'priority' => IncidentPriority::P1,
        'status' => IncidentStatus::Resolved,
        'outcome' => 'TREATED_ON_SCENE',
        'resolved_at' => now(),
    ]);

    $job = new GenerateNdrrmcSitRep($incident);
    app()->call([$job, 'handle']);

    Storage::disk('local')->assertExists("reports/ndrrmc/{$incident->incident_no}.pdf");
});

it('calls NdrrmcReportServiceInterface::submitSitRep with correct data', function () {
    $mock = Mockery::mock(NdrrmcReportServiceInterface::class);
    $mock->shouldReceive('submitSitRep')
        ->once()
        ->withArgs(function (array $data) {
            return isset($data['incident_no'])
                && isset($data['incident_type'])
                && isset($data['priority'])
                && isset($data['created_at']);
        })
        ->andReturn([
            'status' => 'submitted',
            'reference_id' => 'SITREP-STUB-TEST123',
            'xml_payload' => '<SituationReport/>',
        ]);

    $this->app->instance(NdrrmcReportServiceInterface::class, $mock);

    $type = IncidentType::factory()->create();

    $incident = Incident::factory()->create([
        'incident_type_id' => $type->id,
        'priority' => IncidentPriority::P1,
        'status' => IncidentStatus::Resolved,
        'outcome' => 'TREATED_ON_SCENE',
        'resolved_at' => now(),
    ]);

    $job = new GenerateNdrrmcSitRep($incident);
    app()->call([$job, 'handle']);
});

it('creates timeline entry on the incident', function () {
    $type = IncidentType::factory()->create();

    $incident = Incident::factory()->create([
        'incident_type_id' => $type->id,
        'priority' => IncidentPriority::P1,
        'status' => IncidentStatus::Resolved,
        'outcome' => 'TREATED_ON_SCENE',
        'resolved_at' => now(),
    ]);

    $job = new GenerateNdrrmcSitRep($incident);
    app()->call([$job, 'handle']);

    $timelineEntry = $incident->timeline()
        ->where('event_type', 'ndrrmc_sitrep_generated')
        ->first();

    expect($timelineEntry)->not->toBeNull();
    expect($timelineEntry->event_data)->toHaveKey('reference_id');
    expect($timelineEntry->event_data)->toHaveKey('pdf_path');
});

it('listener dispatches GenerateNdrrmcSitRep + GenerateIncidentReport when P1 transitions to RESOLVED', function () {
    Queue::fake();

    $type = IncidentType::factory()->create();
    $incident = Incident::factory()->create([
        'incident_type_id' => $type->id,
        'priority' => IncidentPriority::P1,
        'status' => IncidentStatus::Resolved,
    ]);

    $listener = app(GenerateReportsOnIncidentResolution::class);
    $listener->handle(new IncidentStatusChanged($incident, IncidentStatus::Resolving));

    Queue::assertPushed(GenerateNdrrmcSitRep::class);
    Queue::assertPushed(GenerateIncidentReport::class);
});

it('listener does NOT dispatch GenerateNdrrmcSitRep for P2 transitions', function () {
    Queue::fake();

    $type = IncidentType::factory()->create();
    $incident = Incident::factory()->create([
        'incident_type_id' => $type->id,
        'priority' => IncidentPriority::P2,
        'status' => IncidentStatus::Resolved,
    ]);

    $listener = app(GenerateReportsOnIncidentResolution::class);
    $listener->handle(new IncidentStatusChanged($incident, IncidentStatus::Resolving));

    Queue::assertNotPushed(GenerateNdrrmcSitRep::class);
    Queue::assertPushed(GenerateIncidentReport::class);
});

it('listener is a no-op when status did not transition to RESOLVED', function () {
    Queue::fake();

    $type = IncidentType::factory()->create();
    $incident = Incident::factory()->create([
        'incident_type_id' => $type->id,
        'priority' => IncidentPriority::P1,
        'status' => IncidentStatus::OnScene,
    ]);

    $listener = app(GenerateReportsOnIncidentResolution::class);
    $listener->handle(new IncidentStatusChanged($incident, IncidentStatus::EnRoute));

    Queue::assertNotPushed(GenerateNdrrmcSitRep::class);
    Queue::assertNotPushed(GenerateIncidentReport::class);
});

it('listener is a no-op when previously already RESOLVED', function () {
    Queue::fake();

    $type = IncidentType::factory()->create();
    $incident = Incident::factory()->create([
        'incident_type_id' => $type->id,
        'priority' => IncidentPriority::P1,
        'status' => IncidentStatus::Resolved,
    ]);

    $listener = app(GenerateReportsOnIncidentResolution::class);
    $listener->handle(new IncidentStatusChanged($incident, IncidentStatus::Resolved));

    Queue::assertNotPushed(GenerateNdrrmcSitRep::class);
    Queue::assertNotPushed(GenerateIncidentReport::class);
});
