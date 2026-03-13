<?php

use App\Events\IncidentCreated;
use App\Jobs\GenerateIncidentReport;
use App\Models\Incident;
use App\Models\IncidentType;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Event::fake([
        IncidentCreated::class,
    ]);
});

it('generates a PDF incident report', function () {
    Storage::fake('local');

    $type = IncidentType::factory()->create();

    $incident = Incident::factory()->create([
        'status' => 'RESOLVED',
        'incident_type_id' => $type->id,
        'outcome' => 'FALSE_ALARM',
        'resolved_at' => now(),
        'on_scene_at' => now()->subMinutes(30),
        'vitals' => null,
        'assessment_tags' => ['conscious', 'breathing'],
    ]);

    $job = new GenerateIncidentReport($incident);
    $job->handle();

    $expectedPath = "incident-reports/{$incident->incident_no}.pdf";
    Storage::disk('local')->assertExists($expectedPath);

    expect($incident->fresh()->report_pdf_url)->toBe($expectedPath);
});
