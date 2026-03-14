<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Annual Statistical Summary - {{ $year }}</title>
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
            font-size: 14px;
            margin: 0;
            color: #1e3a5f;
        }
        .header h2 {
            font-size: 12px;
            margin: 2px 0 0;
            color: #555;
            font-weight: normal;
        }
        .header .report-title {
            font-size: 16px;
            font-weight: bold;
            margin-top: 8px;
            color: #1e3a5f;
        }
        .header .report-year {
            font-size: 13px;
            margin-top: 4px;
            color: #333;
        }
        .header .subtitle {
            font-size: 11px;
            margin-top: 2px;
            color: #666;
            font-style: italic;
        }
        .section {
            margin-bottom: 14px;
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
        .positive { color: #16a34a; }
        .negative { color: #dc2626; }
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
        <h1>CITY DISASTER RISK REDUCTION AND MANAGEMENT OFFICE</h1>
        <h2>BUTUAN CITY</h2>
        <div class="report-title">Annual Statistical Summary</div>
        <div class="report-year">{{ $year }}</div>
        <div class="subtitle">For the Office of the City Mayor</div>
    </div>

    <div class="section">
        <div class="section-title">Annual KPI Summary (Year-over-Year)</div>
        <table>
            <tr>
                <th>Metric</th>
                <th>{{ $year }}</th>
                <th>{{ $year - 1 }}</th>
                <th>Change</th>
            </tr>
            @php
                $kpiLabels = [
                    'avg_response_time_min' => 'Avg Response Time (min)',
                    'avg_scene_arrival_time_min' => 'Avg Scene Arrival Time (min)',
                    'resolution_rate' => 'Resolution Rate (%)',
                    'unit_utilization' => 'Unit Utilization (%)',
                    'false_alarm_rate' => 'False Alarm Rate (%)',
                ];
            @endphp
            @foreach ($kpiLabels as $key => $label)
                @php
                    $current = $currentKpis[$key] ?? 0;
                    $prev = $prevKpis[$key] ?? 0;
                    $delta = $current - $prev;
                    $isPercent = str_contains($label, '%');
                @endphp
                <tr>
                    <td>{{ $label }}</td>
                    <td>{{ $isPercent ? number_format($current * 100, 1) . '%' : number_format($current, 1) }}</td>
                    <td>{{ $isPercent ? number_format($prev * 100, 1) . '%' : number_format($prev, 1) }}</td>
                    <td class="{{ $delta >= 0 ? 'positive' : 'negative' }}">
                        {{ $delta >= 0 ? '+' : '' }}{{ $isPercent ? number_format($delta * 100, 1) . '%' : number_format($delta, 1) }}
                    </td>
                </tr>
            @endforeach
        </table>
        <table>
            <tr>
                <th>Total Incidents {{ $year }}</th>
                <th>Total Incidents {{ $year - 1 }}</th>
                <th>Change</th>
            </tr>
            <tr>
                <td>{{ $totalCurrent }}</td>
                <td>{{ $totalPrev }}</td>
                @php $volDelta = $totalCurrent - $totalPrev; @endphp
                <td class="{{ $volDelta >= 0 ? 'positive' : 'negative' }}">
                    {{ $volDelta >= 0 ? '+' : '' }}{{ $volDelta }}
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Monthly Incident Volume</div>
        <table>
            <tr>
                <th>Month</th>
                <th>Count</th>
            </tr>
            @foreach ($monthlyVolume as $m)
                <tr>
                    <td>{{ $m['month'] }}</td>
                    <td>{{ $m['count'] }}</td>
                </tr>
            @endforeach
        </table>
    </div>

    <div class="section">
        <div class="section-title">Incident Type Distribution</div>
        <table>
            <tr>
                <th>Type</th>
                <th>Count</th>
                <th>%</th>
            </tr>
            @foreach ($typeDistribution as $t)
                <tr>
                    <td>{{ $t['name'] }}</td>
                    <td>{{ $t['count'] }}</td>
                    <td>{{ $totalCurrent > 0 ? number_format(($t['count'] / $totalCurrent) * 100, 1) : '0.0' }}%</td>
                </tr>
            @endforeach
        </table>
    </div>

    <div class="section">
        <div class="section-title">Priority Distribution</div>
        <table>
            <tr>
                <th>Priority</th>
                <th>Count</th>
                <th>%</th>
            </tr>
            @foreach ($priorityDistribution as $p)
                <tr>
                    <td>{{ $p['priority'] }}</td>
                    <td>{{ $p['count'] }}</td>
                    <td>{{ $totalCurrent > 0 ? number_format(($p['count'] / $totalCurrent) * 100, 1) : '0.0' }}%</td>
                </tr>
            @endforeach
        </table>
    </div>

    <div class="footer">
        Generated by Sentinel on {{ now()->format('Y-m-d H:i:s') }}
    </div>
</body>
</html>
