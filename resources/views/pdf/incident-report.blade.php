<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Incident Report - {{ $incident->incident_no }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #1a1a1a;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #1e3a5f;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .header h1 {
            font-size: 16px;
            margin: 0;
            color: #1e3a5f;
        }
        .header h2 {
            font-size: 12px;
            margin: 2px 0 0;
            color: #555;
            font-weight: normal;
        }
        .header .incident-no {
            font-size: 14px;
            font-weight: bold;
            margin-top: 8px;
        }
        .section {
            margin-bottom: 12px;
        }
        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #1e3a5f;
            border-bottom: 1px solid #ccc;
            padding-bottom: 3px;
            margin-bottom: 6px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        table td, table th {
            padding: 4px 8px;
            border: 1px solid #ddd;
            font-size: 10px;
        }
        table th {
            background-color: #f0f4f8;
            text-align: left;
            font-weight: bold;
        }
        .field-label {
            font-weight: bold;
            color: #333;
            width: 140px;
        }
        .tags {
            display: inline;
        }
        .tag {
            display: inline-block;
            background: #e8f0fe;
            color: #1e3a5f;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9px;
            margin-right: 4px;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 9px;
            color: #888;
            border-top: 1px solid #ccc;
            padding-top: 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>CDRRMO - City of Butuan</h1>
        <h2>City Disaster Risk Reduction and Management Office</h2>
        <div class="incident-no">Incident Report: {{ $incident->incident_no }}</div>
    </div>

    <div class="section">
        <div class="section-title">Incident Information</div>
        <table>
            <tr>
                <td class="field-label">Incident No.</td>
                <td>{{ $incident->incident_no }}</td>
                <td class="field-label">Type</td>
                <td>{{ $incident->incidentType?->name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="field-label">Priority</td>
                <td>{{ $incident->priority?->value ?? 'N/A' }}</td>
                <td class="field-label">Status</td>
                <td>{{ $incident->status?->value ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="field-label">Outcome</td>
                <td>{{ $incident->outcome ?? 'N/A' }}</td>
                <td class="field-label">Hospital</td>
                <td>{{ $incident->hospital ?? 'N/A' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Location</div>
        <table>
            <tr>
                <td class="field-label">Address</td>
                <td>{{ $incident->location_text ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="field-label">Barangay</td>
                <td>{{ $incident->barangay?->name ?? 'N/A' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Timeline</div>
        <table>
            <tr>
                <td class="field-label">Created</td>
                <td>{{ $incident->created_at?->format('Y-m-d H:i:s') ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="field-label">Dispatched</td>
                <td>{{ $incident->dispatched_at?->format('Y-m-d H:i:s') ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="field-label">Acknowledged</td>
                <td>{{ $incident->acknowledged_at?->format('Y-m-d H:i:s') ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="field-label">En Route</td>
                <td>{{ $incident->en_route_at?->format('Y-m-d H:i:s') ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="field-label">On Scene</td>
                <td>{{ $incident->on_scene_at?->format('Y-m-d H:i:s') ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="field-label">Resolved</td>
                <td>{{ $incident->resolved_at?->format('Y-m-d H:i:s') ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="field-label">Scene Time</td>
                <td>{{ $incident->scene_time_sec ? gmdate('H:i:s', $incident->scene_time_sec) : 'N/A' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Assigned Units</div>
        @if($incident->assignedUnits && $incident->assignedUnits->count() > 0)
            <table>
                <tr>
                    <th>Unit ID</th>
                    <th>Callsign</th>
                    <th>Assigned At</th>
                    <th>Acknowledged At</th>
                </tr>
                @foreach($incident->assignedUnits as $unit)
                    <tr>
                        <td>{{ $unit->id }}</td>
                        <td>{{ $unit->callsign }}</td>
                        <td>{{ $unit->pivot->assigned_at ?? 'N/A' }}</td>
                        <td>{{ $unit->pivot->acknowledged_at ?? 'N/A' }}</td>
                    </tr>
                @endforeach
            </table>
        @else
            <p>No units assigned.</p>
        @endif
    </div>

    <div class="section">
        <div class="section-title">Checklist Completion</div>
        <p>{{ $incident->checklist_pct ?? 0 }}% complete</p>
    </div>

    @if($incident->vitals)
        <div class="section">
            <div class="section-title">Patient Vitals</div>
            <table>
                <tr>
                    <th>Metric</th>
                    <th>Value</th>
                </tr>
                @foreach($incident->vitals as $key => $value)
                    <tr>
                        <td>{{ str_replace('_', ' ', ucfirst($key)) }}</td>
                        <td>{{ $value }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    @if($incident->assessment_tags && count($incident->assessment_tags) > 0)
        <div class="section">
            <div class="section-title">Assessment Tags</div>
            <p>
                @foreach($incident->assessment_tags as $tag)
                    <span class="tag">{{ str_replace('_', ' ', ucfirst($tag)) }}</span>
                @endforeach
            </p>
        </div>
    @endif

    @if($incident->closure_notes)
        <div class="section">
            <div class="section-title">Closure Notes</div>
            <p>{{ $incident->closure_notes }}</p>
        </div>
    @endif

    <div class="footer">
        Generated on {{ now()->format('Y-m-d H:i:s') }} | CDRRMO Butuan City IRMS
    </div>
</body>
</html>
