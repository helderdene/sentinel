<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NDRRMC Situation Report - {{ $incident->incident_no }}</title>
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
            font-size: 13px;
            margin: 0;
            color: #1e3a5f;
        }
        .header h2 {
            font-size: 16px;
            margin: 6px 0 0;
            color: #1e3a5f;
            font-weight: bold;
        }
        .header .incident-no {
            font-size: 12px;
            margin-top: 6px;
            color: #333;
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
        <h1>NATIONAL DISASTER RISK REDUCTION AND MANAGEMENT COUNCIL</h1>
        <h2>SITUATION REPORT</h2>
        <div class="incident-no">Incident: {{ $incident->incident_no }}</div>
    </div>

    <div class="section">
        <div class="section-title">Incident Details</div>
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
                <td class="field-label">Status / Outcome</td>
                <td>{{ $incident->outcome ?? $incident->status?->value ?? 'N/A' }}</td>
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
            @if ($incident->coordinates)
                <tr>
                    <td class="field-label">Coordinates</td>
                    <td>{{ $incident->coordinates->getLatitude() }}, {{ $incident->coordinates->getLongitude() }}</td>
                </tr>
            @endif
        </table>
    </div>

    <div class="section">
        <div class="section-title">Timeline</div>
        @if ($incident->timeline && $incident->timeline->count() > 0)
            <table>
                <tr>
                    <th>Event</th>
                    <th>Timestamp</th>
                    <th>Actor</th>
                </tr>
                @foreach ($incident->timeline->sortBy('created_at') as $entry)
                    <tr>
                        <td>{{ str_replace('_', ' ', ucfirst($entry->event_type)) }}</td>
                        <td>{{ $entry->created_at->format('Y-m-d H:i:s') }}</td>
                        <td>{{ $entry->actor_type === 'system' ? 'System' : ($entry->actor_id ?? 'N/A') }}</td>
                    </tr>
                @endforeach
            </table>
        @else
            <p>No timeline entries recorded.</p>
        @endif
    </div>

    <div class="section">
        <div class="section-title">Response</div>
        <table>
            <tr>
                <td class="field-label">Units Deployed</td>
                <td>
                    @if ($incident->assignedUnits && $incident->assignedUnits->count() > 0)
                        @foreach ($incident->assignedUnits as $unit)
                            {{ $unit->callsign }}@if (!$loop->last), @endif
                        @endforeach
                    @else
                        None
                    @endif
                </td>
            </tr>
            <tr>
                <td class="field-label">Scene Time</td>
                <td>{{ $incident->scene_time_sec ? gmdate('H:i:s', $incident->scene_time_sec) : 'N/A' }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Reference ID: {{ $referenceId }} | Submitted via Sentinel on {{ now()->format('Y-m-d H:i:s') }}
    </div>
</body>
</html>
