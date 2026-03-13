# Phase 7: Analytics - Research

**Researched:** 2026-03-13
**Domain:** KPI Dashboard, Choropleth Heatmap, Compliance Report Generation (PDF/CSV)
**Confidence:** HIGH

## Summary

Phase 7 is a read-only analytics and reporting layer. It has three pillars: (1) a KPI dashboard with 5 metrics, sparkline cards, and detailed line charts; (2) a choropleth heatmap using MapLibre GL JS with barangay polygon boundaries from PostGIS; and (3) automated and on-demand compliance report generation (DILG monthly, NDRRMC SitRep, quarterly, annual) as PDF and CSV.

The project already has the core building blocks in place. MapLibre GL JS v5 is installed and used extensively in the dispatch console (useDispatchMap composable). DomPDF (barryvdh/laravel-dompdf v3.1) is installed and used for incident closure PDFs (GenerateIncidentReport job). The NDRRMC stub service (StubNdrrmcReportService) is already bound in the service container. Barangay boundaries are stored as PostGIS polygons with a spatial index. The Incident model has all lifecycle timestamps needed for KPI calculation. The authorization gate `view-analytics` already exists for supervisor and admin roles.

The main new additions are: (1) Chart.js 4 via vue-chartjs for dashboard charts and sparklines (new npm dependency), (2) league/csv for CSV export (new composer dependency), (3) a new AnalyticsController, an AnalyticsService for KPI computation, and report generation jobs, (4) a scheduled command in routes/console.php for monthly DILG report generation, and (5) a new useAnalyticsMap composable for the choropleth (distinct from the dispatch map).

**Primary recommendation:** Use Chart.js 4 + vue-chartjs for all charting (both sparklines and detailed charts in one library), MapLibre GL JS fill layers with barangay GeoJSON for the choropleth, DomPDF for all PDF reports, league/csv for CSV exports, and Laravel's task scheduler for the monthly DILG job.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- 5 metric cards in a responsive row: avg response time, avg scene arrival time, resolution rate, unit utilization, false alarm rate
- Each card shows: current value, trend arrow (up/down with percentage), small sparkline chart for the selected period
- Below the cards, detailed line charts per KPI for the selected date range -- users can toggle which KPIs to show
- Sticky horizontal filter bar pinned below the page header with: date range presets (7d / 30d / 90d / 365d / Custom date picker), incident type dropdown, priority selector, barangay dropdown
- Default filter: last 30 days, all types, all priorities, all barangays
- Choropleth map using MapLibre GL JS with barangay polygons colored by incident density (light-to-dark gradient)
- Hover tooltip shows barangay name and incident count
- Click popup shows: barangay name, total incidents, top 5 incident types breakdown, priority breakdown, link to filter KPI dashboard for that barangay
- PNG export via "Export" button that captures MapLibre canvas with legend and filter labels included
- Shares the same filter bar as the KPI dashboard -- filters apply across both views
- Central "Reports" download center page listing all generated reports with date, type, and download links (PDF + CSV where applicable)
- DILG monthly: Scheduled Laravel job on 1st of each month generates PDF + CSV aggregating incidents by type, priority, barangay, outcome. Stored in reports download center.
- NDRRMC SitRep: Auto-generated on P1 incident closure. PDF stored in download center AND logged as timeline entry on the incident with link to PDF. Stubbed XML submission via Phase 6 NDRRMC stub service triggered in background.
- Quarterly performance: On-demand from reports page. User selects period (Q1 2026, etc.), generates in background, notification when ready. Content: KPI trend lines over quarter, incident volume bar chart by week, top 10 barangays by incident count, breakdown by type/priority, comparison to previous quarter.
- Annual summary: On-demand from reports page. User selects year, generates in background. Year-over-year comparison for Mayor's Office.
- Dedicated /analytics route with tab bar: Dashboard, Heatmap, Reports
- Sidebar link "Analytics" in the main AppLayout sidebar -- visible to supervisor and admin roles only
- Dispatchers, operators, and responders do NOT see the analytics sidebar link
- Shared filter state across all three tabs -- changing filters on Dashboard carries over to Heatmap
- Filter state stored in URL query params for shareable/bookmarkable URLs

### Claude's Discretion
- Sparkline chart library choice (lightweight inline charts)
- Line chart library for detailed KPI trends (Chart.js, ApexCharts, or similar)
- Choropleth color scale (sequential single-hue or multi-hue)
- PDF layout and styling for compliance reports
- Report storage path and cleanup strategy
- Loading states and skeleton designs
- Exact tab component implementation

### Deferred Ideas (OUT OF SCOPE)
None -- discussion stayed within phase scope
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| ANLTCS-01 | KPI dashboard with 5 metrics filterable by date range, type, priority, barangay | AnalyticsService computes metrics from Incident model lifecycle timestamps; Chart.js 4 + vue-chartjs renders sparklines and line charts; filter state in URL query params |
| ANLTCS-02 | Incident heatmap as choropleth map with barangay polygons, filters, PNG export | MapLibre GL JS fill layer with barangay boundary GeoJSON from PostGIS; preserveDrawingBuffer for canvas PNG export; shared filter composable |
| ANLTCS-03 | DILG monthly incident report auto-generated on 1st as PDF + CSV | Scheduled job via Schedule::job() in routes/console.php; DomPDF for PDF; league/csv for CSV; stored to reports storage path |
| ANLTCS-04 | NDRRMC SitRep auto-generated on P1 closure with stubbed XML submission and PDF fallback | Hook into ResponderController::resolve() to dispatch SitRep job when priority is P1; reuse StubNdrrmcReportService; generate PDF and log timeline entry |
| ANLTCS-05 | Quarterly performance report with KPI trends and charts as PDF | On-demand queued job; DomPDF with Blade template containing inline charts rendered as SVG/table; comparison to previous quarter |
| ANLTCS-06 | Annual statistical summary for Mayor's Office with year-over-year comparison as PDF | On-demand queued job; DomPDF with Blade template; year-over-year comparison tables and trend data |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Chart.js | 4.x | Chart rendering (sparklines + line charts + bar charts) | Tree-shakable, 11KB gzipped for used components, native TypeScript types, built-in responsive scaling |
| vue-chartjs | 5.x | Vue 3 wrapper for Chart.js | Official Vue 3 Chart.js integration, Composition API support, reactive props |
| maplibre-gl | 5.20.0 | Choropleth map rendering | Already installed, used in dispatch console, supports fill layers for polygon choropleth |
| barryvdh/laravel-dompdf | 3.1 | PDF report generation | Already installed, used for incident closure PDFs, proven pattern |
| league/csv | 9.x | CSV export generation | PHP League standard, lightweight, streaming writer for large datasets |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| @vueuse/core | 12.8.2 | Debounce for filter inputs, URL query sync | Already installed, used across project |
| lucide-vue-next | 0.468.0 | Icons for metric cards, filter bar, tabs | Already installed, project standard |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Chart.js + vue-chartjs | ApexCharts | ApexCharts is heavier (~500KB), more features but overkill for 5 KPI charts |
| Chart.js + vue-chartjs | vue-sparklines + separate chart lib | Two libraries vs one; Chart.js can do both sparklines and full charts |
| league/csv | Native fputcsv | league/csv handles encoding, BOM, streaming; fputcsv has edge cases with special chars |
| DomPDF | Browsershot/Puppeteer | DomPDF already installed and proven; Puppeteer needs Chrome binary |

**Discretion Recommendation (Sparkline + Chart Library):** Use Chart.js 4 + vue-chartjs for everything. Chart.js can render sparklines by disabling axes, legends, and tooltips (minimal config). This avoids adding a second charting dependency. Tree-shaking keeps the bundle lean -- only import LineController, PointElement, LineElement, CategoryScale, LinearScale, BarController, BarElement.

**Discretion Recommendation (Choropleth Color Scale):** Use a sequential single-hue blue scale (5 stops: #eff6ff, #bfdbfe, #60a5fa, #2563eb, #1d4ed8). Blue is neutral, high contrast, accessible for color-blind users, and matches the existing IRMS color system where blue represents operational data.

**Discretion Recommendation (Report Storage):** Store reports in `storage/app/reports/{type}/{YYYY-MM}/filename.pdf`. Implement a cleanup command to purge reports older than 12 months (deferred -- not needed for v1 launch).

**Discretion Recommendation (Tab Component):** Use Reka UI TabsRoot/TabsList/TabsTrigger/TabsContent for the Dashboard/Heatmap/Reports tab bar, matching existing project UI component patterns.

**Discretion Recommendation (Loading States):** Use CSS animation pulsing skeleton cards (3 gray rectangles per card) for KPI loading, and a shimmer overlay on the map container for choropleth loading.

**Installation:**
```bash
npm install chart.js vue-chartjs
composer require league/csv
```

## Architecture Patterns

### Recommended Project Structure
```
app/
├── Contracts/
│   └── AnalyticsServiceInterface.php     # KPI computation contract
├── Services/
│   └── AnalyticsService.php              # KPI queries, metric aggregation
├── Http/
│   ├── Controllers/
│   │   └── AnalyticsController.php       # Dashboard, heatmap, reports pages + API
│   └── Requests/
│       ├── AnalyticsFilterRequest.php     # Shared filter validation
│       └── GenerateReportRequest.php      # On-demand report request validation
├── Jobs/
│   ├── GenerateDilgMonthlyReport.php     # Scheduled monthly DILG PDF+CSV
│   ├── GenerateNdrrmcSitRep.php          # Auto on P1 closure
│   ├── GenerateQuarterlyReport.php       # On-demand quarterly
│   └── GenerateAnnualReport.php          # On-demand annual
├── Models/
│   └── GeneratedReport.php               # Report metadata model (new table)

resources/
├── js/
│   ├── pages/
│   │   └── analytics/
│   │       ├── Dashboard.vue             # KPI cards + line charts
│   │       ├── Heatmap.vue               # Choropleth map
│   │       └── Reports.vue               # Report download center
│   ├── composables/
│   │   ├── useAnalyticsFilters.ts        # Shared filter state + URL sync
│   │   └── useAnalyticsMap.ts            # Choropleth map composable
│   ├── components/
│   │   └── analytics/
│   │       ├── KpiCard.vue               # Single metric card with sparkline
│   │       ├── KpiLineChart.vue          # Detailed line chart wrapper
│   │       ├── FilterBar.vue             # Sticky horizontal filter bar
│   │       ├── ChoroplethLegend.vue      # Color scale legend
│   │       └── ReportRow.vue             # Single report in download center
│   └── types/
│       └── analytics.ts                  # TypeScript types for analytics
├── views/
│   └── pdf/
│       ├── dilg-monthly.blade.php        # DILG report template
│       ├── ndrrmc-sitrep.blade.php       # NDRRMC SitRep PDF template
│       ├── quarterly-report.blade.php    # Quarterly performance template
│       └── annual-summary.blade.php      # Annual summary template

routes/
└── console.php                           # Schedule DILG monthly job

database/
└── migrations/
    └── XXXX_create_generated_reports_table.php
```

### Pattern 1: AnalyticsService for KPI Computation
**What:** A service class behind an interface that computes all 5 KPIs from the Incident model, accepting filter parameters.
**When to use:** All KPI data fetching -- controller delegates to service, jobs reuse for report data.
**Example:**
```php
// app/Contracts/AnalyticsServiceInterface.php
interface AnalyticsServiceInterface
{
    /**
     * @param array{start_date: string, end_date: string, incident_type_id?: int, priority?: string, barangay_id?: int} $filters
     * @return array{avg_response_time: float|null, avg_scene_arrival_time: float|null, resolution_rate: float, unit_utilization: float, false_alarm_rate: float}
     */
    public function computeKpis(array $filters): array;

    /**
     * @return array<int, array{date: string, value: float}>
     */
    public function kpiTimeSeries(string $metric, array $filters, string $interval = 'day'): array;

    /**
     * @return array<int, array{barangay_id: int, name: string, incident_count: int}>
     */
    public function incidentDensityByBarangay(array $filters): array;
}
```

### Pattern 2: Shared Filter Composable with URL Sync
**What:** A composable that manages filter state (date range, incident type, priority, barangay) and syncs with URL query params via Inertia router.
**When to use:** Shared across Dashboard and Heatmap tabs so filter changes persist across tab switches.
**Example:**
```typescript
// resources/js/composables/useAnalyticsFilters.ts
export function useAnalyticsFilters() {
    // Read initial state from URL query params (Inertia page.props.ziggy.query)
    // Provide reactive refs for each filter
    // On change, update URL via router.get with preserveState + preserveScroll
    // Return refs + a computed query object for API calls
}
```

### Pattern 3: GeneratedReport Model for Report Download Center
**What:** A model/table tracking all generated reports with type, period, file path, and status.
**When to use:** Reports page lists these records; download links serve files from storage.
**Example:**
```php
// generated_reports table
Schema::create('generated_reports', function (Blueprint $table) {
    $table->id();
    $table->string('type', 30);         // dilg_monthly, ndrrmc_sitrep, quarterly, annual
    $table->string('title');
    $table->string('period', 30);        // 2026-03, Q1-2026, 2026, INC-2026-00042
    $table->string('file_path');          // storage path to PDF
    $table->string('csv_path')->nullable();
    $table->string('status', 20)->default('generating'); // generating, ready, failed
    $table->foreignId('generated_by')->nullable()->constrained('users');
    $table->timestamps();
});
```

### Pattern 4: Choropleth Map with MapLibre Fill Layer
**What:** A new composable (useAnalyticsMap) that creates a MapLibre map with barangay polygon GeoJSON rendered as a fill layer, colored by incident density using data-driven styling.
**When to use:** Heatmap tab.
**Example:**
```typescript
// Choropleth fill layer with data-driven color
map.addLayer({
    id: 'barangay-fill',
    type: 'fill',
    source: 'barangays',
    paint: {
        'fill-color': [
            'interpolate',
            ['linear'],
            ['get', 'incident_count'],
            0, '#eff6ff',
            5, '#bfdbfe',
            15, '#60a5fa',
            30, '#2563eb',
            50, '#1d4ed8',
        ],
        'fill-opacity': 0.7,
    },
});
```

### Pattern 5: PNG Export with preserveDrawingBuffer
**What:** Initialize MapLibre with preserveDrawingBuffer in canvasContextAttributes (v5 API), then use canvas.toDataURL() on export button click.
**When to use:** Heatmap PNG export.
**Important:** In MapLibre GL JS v5+, WebGL context options must be inside `canvasContextAttributes`:
```typescript
new maplibregl.Map({
    container: containerId,
    style: LIGHT_STYLE,
    center: BUTUAN_CENTER,
    zoom: BUTUAN_ZOOM,
    canvasContextAttributes: {
        preserveDrawingBuffer: true,
    },
});
```

### Anti-Patterns to Avoid
- **Computing KPIs in the frontend:** All 5 metrics should be computed server-side with SQL aggregations. The dispatch console computes live metrics client-side because it needs real-time reactivity -- analytics queries historical data and should use database-level aggregation.
- **Loading all incidents to the frontend:** Never send raw incident records. Send pre-computed KPI values and time-series arrays from the backend.
- **Using the dispatch map composable for choropleth:** The dispatch map has incident markers, unit markers, connection lines, and animation logic. The choropleth map is fundamentally different (polygon fill layers). Create a separate composable.
- **Generating PDF synchronously in HTTP request:** All report generation must be queued via ShouldQueue jobs. Return a "generating" status and poll/notify when ready.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| KPI time-series aggregation | Custom PHP loops over incidents | PostgreSQL DATE_TRUNC + GROUP BY | Database handles millions of rows; PHP loop OOMs |
| CSV generation | fputcsv wrapper | league/csv Writer | Handles UTF-8 BOM, proper escaping, streaming |
| Sparkline rendering | Canvas 2D API manually | Chart.js with axes/legend disabled | Responsive, retina-aware, tooltip optional |
| Choropleth color mapping | Manual if/else color assignment | MapLibre interpolate expression | GPU-accelerated, smooth gradients, handles edge cases |
| Map PNG export | html2canvas on map container | MapLibre canvas.toDataURL() | html2canvas cannot capture WebGL; MapLibre native export works |
| Scheduled monthly job | Manual cron entry | Laravel Schedule::job()->monthlyOn(1, '00:00') | Framework-managed, testable, logs to Horizon |

**Key insight:** Analytics is mostly a read-heavy query + rendering problem. The heavy lifting is SQL aggregation (PostgreSQL is excellent at this) and client-side chart rendering (Chart.js/MapLibre handle this). Don't build custom aggregation pipelines in PHP.

## Common Pitfalls

### Pitfall 1: N+1 Queries in KPI Aggregation
**What goes wrong:** Loading incidents with relationships then computing metrics in PHP creates N+1 queries and memory issues.
**Why it happens:** Treating analytics like a CRUD read -- loading models instead of using aggregate queries.
**How to avoid:** Use raw SQL or query builder for aggregations: `Incident::query()->selectRaw('AVG(EXTRACT(EPOCH FROM (dispatched_at - created_at))) as avg_response_time')`. Never `Incident::all()` for analytics.
**Warning signs:** Slow page loads, memory exhaustion on large datasets.

### Pitfall 2: MapLibre v5 Canvas Export Blank Image
**What goes wrong:** `map.getCanvas().toDataURL()` returns a blank/transparent PNG.
**Why it happens:** WebGL clears the drawing buffer after each frame. In MapLibre v5, `preserveDrawingBuffer` must be set inside `canvasContextAttributes` (not as a top-level option like in v4).
**How to avoid:** Always set `canvasContextAttributes: { preserveDrawingBuffer: true }` in the Map constructor. Alternatively, capture during the `idle` event.
**Warning signs:** Blank exported images, works sometimes but not others.

### Pitfall 3: DomPDF CSS Limitations
**What goes wrong:** Flexbox, CSS Grid, modern CSS features don't render in PDF.
**Why it happens:** DomPDF uses a limited CSS 2.1 rendering engine.
**How to avoid:** Use table-based layouts and inline styles in PDF Blade templates. No flexbox, no grid, no CSS variables, no color-mix(). Use the existing incident-report.blade.php as a template reference.
**Warning signs:** Broken PDF layouts, missing styles.

### Pitfall 4: Timezone Confusion in Date Filters
**What goes wrong:** "Last 30 days" computed differently on server (UTC) vs client (Asia/Manila, UTC+8).
**Why it happens:** Date range filters parsed in different timezones.
**How to avoid:** Always compute date boundaries server-side in the controller. Client sends preset name ("30d") or explicit dates. Server computes `Carbon::now('Asia/Manila')->subDays(30)->startOfDay()`.
**Warning signs:** Off-by-one day errors, missing incidents at period boundaries.

### Pitfall 5: Barangay GeoJSON Too Large for Frontend
**What goes wrong:** Sending 86 barangay polygons with full PostGIS precision creates a massive GeoJSON payload.
**Why it happens:** PostGIS polygons have high coordinate precision (15+ decimal places) and many vertices.
**How to avoid:** Create a dedicated API endpoint that returns simplified GeoJSON. Use PostGIS `ST_SimplifyPreserveTopology` to reduce vertices. Cache the result aggressively (barangay boundaries don't change). Alternatively, serve a static GeoJSON file.
**Warning signs:** Slow initial heatmap load, large network payload.

### Pitfall 6: Report Generation Race Condition
**What goes wrong:** Multiple DILG reports generated for the same month, or user triggers duplicate on-demand reports.
**Why it happens:** Scheduled job runs while a manual trigger is in progress, or user double-clicks generate button.
**How to avoid:** Use `GeneratedReport::firstOrCreate` with type + period as unique constraint. Check for existing "generating" status before dispatching job. Add a unique constraint on `(type, period)` in the migration.
**Warning signs:** Duplicate report entries, wasted queue processing.

## Code Examples

### KPI Computation with PostgreSQL Aggregation
```php
// Source: Established project pattern from BarangayLookupService (raw SQL for PostGIS)
// All times in seconds, converted to minutes in the service response

$query = Incident::query()
    ->where('status', IncidentStatus::Resolved->value)
    ->whereBetween('created_at', [$startDate, $endDate]);

if ($filters['incident_type_id'] ?? null) {
    $query->where('incident_type_id', $filters['incident_type_id']);
}
if ($filters['priority'] ?? null) {
    $query->where('priority', $filters['priority']);
}
if ($filters['barangay_id'] ?? null) {
    $query->where('barangay_id', $filters['barangay_id']);
}

$metrics = $query->selectRaw("
    AVG(EXTRACT(EPOCH FROM (dispatched_at - created_at))) / 60.0 as avg_response_time_min,
    AVG(EXTRACT(EPOCH FROM (on_scene_at - dispatched_at))) / 60.0 as avg_scene_arrival_time_min,
    COUNT(*) as total_resolved
")->first();
```

### Barangay Incident Density Query
```php
// Source: Established project pattern (PostGIS + Eloquent)
$density = Barangay::query()
    ->select('barangays.id', 'barangays.name')
    ->selectRaw('COUNT(incidents.id) as incident_count')
    ->leftJoin('incidents', function ($join) use ($startDate, $endDate) {
        $join->on('barangays.id', '=', 'incidents.barangay_id')
            ->whereBetween('incidents.created_at', [$startDate, $endDate]);
    })
    ->groupBy('barangays.id', 'barangays.name')
    ->get();
```

### Barangay GeoJSON Endpoint
```php
// Return simplified polygon boundaries as GeoJSON FeatureCollection
// Cache aggressively -- boundaries don't change
$geojson = Cache::rememberForever('barangay-boundaries-geojson', function () {
    $barangays = Barangay::query()
        ->selectRaw("id, name, ST_AsGeoJSON(ST_SimplifyPreserveTopology(boundary::geometry, 0.0005)) as geojson")
        ->whereNotNull('boundary')
        ->get();

    return [
        'type' => 'FeatureCollection',
        'features' => $barangays->map(fn ($b) => [
            'type' => 'Feature',
            'id' => $b->id,
            'geometry' => json_decode($b->geojson),
            'properties' => ['id' => $b->id, 'name' => $b->name],
        ])->all(),
    ];
});
```

### Chart.js Sparkline Configuration (Minimal)
```typescript
// Source: Chart.js docs - chart options for sparkline rendering
import { Line } from 'vue-chartjs';
import {
    CategoryScale,
    Chart as ChartJS,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
} from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, LineController);

// Sparkline config: no axes, no legend, no tooltips, thin line
const sparklineOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip: { enabled: false } },
    scales: {
        x: { display: false },
        y: { display: false },
    },
    elements: {
        point: { radius: 0 },
        line: { borderWidth: 2, tension: 0.4 },
    },
};
```

### Scheduled DILG Monthly Report
```php
// routes/console.php
use App\Jobs\GenerateDilgMonthlyReport;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new GenerateDilgMonthlyReport)->monthlyOn(1, '00:00')
    ->timezone('Asia/Manila')
    ->description('Generate DILG monthly incident report');
```

### MapLibre Choropleth with Popup
```typescript
// Hover tooltip
map.on('mousemove', 'barangay-fill', (e) => {
    if (e.features && e.features.length > 0) {
        const feature = e.features[0];
        popup.setLngLat(e.lngLat)
            .setHTML(`<strong>${feature.properties.name}</strong><br>${feature.properties.incident_count} incidents`)
            .addTo(map);
    }
});

// Click popup with detailed breakdown
map.on('click', 'barangay-fill', (e) => {
    if (e.features && e.features.length > 0) {
        const barangayId = e.features[0].properties.id;
        // Fetch detailed breakdown from API, show in popup
    }
});
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Chart.js 3 with vue-chartjs 4 | Chart.js 4 with vue-chartjs 5 | Chart.js 4.0 (Feb 2023) | Tree-shakable, better TypeScript, smaller bundles |
| MapLibre v4 preserveDrawingBuffer top-level | MapLibre v5 canvasContextAttributes | MapLibre 5.0 (2025) | Must nest WebGL options inside canvasContextAttributes |
| league/csv 8.x | league/csv 9.x | 2024 | New streaming API, better memory for large exports |
| Laravel Schedule in Kernel | Schedule in routes/console.php | Laravel 11+ (2024) | No more Kernel.php; schedules in routes/console.php |

**Deprecated/outdated:**
- `vue-chart-3` package: Abandoned wrapper; use official `vue-chartjs` v5 instead
- `MapOptions.preserveDrawingBuffer` as top-level: Moved to `canvasContextAttributes` in MapLibre v5

## Open Questions

1. **Barangay GeoJSON Source File**
   - What we know: Barangay boundaries are seeded from `docs/brgy.json` into PostGIS. The seeder parses MultiPolygon geometry.
   - What's unclear: Whether to serve GeoJSON from PostGIS at runtime (with ST_SimplifyPreserveTopology) or pre-generate a static GeoJSON file at build time.
   - Recommendation: Use a cached API endpoint that queries PostGIS with simplification. This avoids maintaining a separate static file and ensures consistency with the database.

2. **NDRRMC SitRep PDF Content**
   - What we know: The XML schema and NDRRMC report format are not publicly documented (noted as a blocker in STATE.md). StubNdrrmcReportService generates XML with basic SitRep fields.
   - What's unclear: Exact PDF layout expected by OCD Caraga.
   - Recommendation: Model the PDF after the XML structure from StubNdrrmcReportService, using a professional government report style. This can be refined when agency contact is established.

3. **Chart.js Bundle Size Impact**
   - What we know: Chart.js 4 is tree-shakable. Only importing LineController, BarController, and required elements keeps the bundle small.
   - What's unclear: Exact bundle size impact on the existing Vite build.
   - Recommendation: Register only needed Chart.js components globally in a single setup file. Monitor bundle size with `npm run build`.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 4 |
| Config file | phpunit.xml |
| Quick run command | `php artisan test --compact --filter=Analytics` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements -> Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| ANLTCS-01 | KPI dashboard returns 5 computed metrics with filters | feature | `php artisan test --compact tests/Feature/Analytics/KpiDashboardTest.php -x` | Wave 0 |
| ANLTCS-01 | AnalyticsService computes correct metric values | unit | `php artisan test --compact tests/Unit/AnalyticsServiceTest.php -x` | Wave 0 |
| ANLTCS-02 | Heatmap endpoint returns barangay GeoJSON with density | feature | `php artisan test --compact tests/Feature/Analytics/HeatmapTest.php -x` | Wave 0 |
| ANLTCS-03 | DILG monthly job generates PDF and CSV | feature | `php artisan test --compact tests/Feature/Analytics/DilgReportTest.php -x` | Wave 0 |
| ANLTCS-04 | NDRRMC SitRep generated on P1 closure | feature | `php artisan test --compact tests/Feature/Analytics/NdrrmcSitRepTest.php -x` | Wave 0 |
| ANLTCS-05 | Quarterly report job generates PDF | feature | `php artisan test --compact tests/Feature/Analytics/QuarterlyReportTest.php -x` | Wave 0 |
| ANLTCS-06 | Annual summary job generates PDF | feature | `php artisan test --compact tests/Feature/Analytics/AnnualReportTest.php -x` | Wave 0 |
| AUTH | Analytics pages restricted to supervisor/admin | feature | `php artisan test --compact tests/Feature/Analytics/AnalyticsAccessTest.php -x` | Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --compact --filter=Analytics`
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/Analytics/KpiDashboardTest.php` -- covers ANLTCS-01
- [ ] `tests/Unit/AnalyticsServiceTest.php` -- covers ANLTCS-01 metric computation
- [ ] `tests/Feature/Analytics/HeatmapTest.php` -- covers ANLTCS-02
- [ ] `tests/Feature/Analytics/DilgReportTest.php` -- covers ANLTCS-03
- [ ] `tests/Feature/Analytics/NdrrmcSitRepTest.php` -- covers ANLTCS-04
- [ ] `tests/Feature/Analytics/QuarterlyReportTest.php` -- covers ANLTCS-05
- [ ] `tests/Feature/Analytics/AnnualReportTest.php` -- covers ANLTCS-06
- [ ] `tests/Feature/Analytics/AnalyticsAccessTest.php` -- covers role access
- [ ] `database/migrations/XXXX_create_generated_reports_table.php` -- new table
- [ ] Framework install: `npm install chart.js vue-chartjs && composer require league/csv`

## Sources

### Primary (HIGH confidence)
- Codebase inspection: Incident model, Barangay model, IncidentOutcome enum, IncidentStatus enum, IncidentPriority enum, GenerateIncidentReport job, StubNdrrmcReportService, useDispatchMap composable, AppSidebar.vue, AppServiceProvider.php, routes/web.php, routes/console.php, database migrations
- MapLibre GL JS docs (maplibre.org) -- fill layer, choropleth examples, preserveDrawingBuffer in canvasContextAttributes
- Laravel 12 Task Scheduling docs -- Schedule in routes/console.php, monthlyOn()

### Secondary (MEDIUM confidence)
- Chart.js 4 tree-shaking and vue-chartjs 5 Composition API setup -- verified via vue-chartjs.org and multiple 2025/2026 comparison articles
- league/csv 9.x Writer API -- verified via csv.thephpleague.com documentation
- MapLibre v5 canvasContextAttributes change -- verified via GitHub issue #337 and MapLibre changelog

### Tertiary (LOW confidence)
- Exact Chart.js 4 bundle size (11KB gzipped claim from web search) -- needs validation with actual tree-shaken build

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All core libraries either already installed or well-established PHP/JS ecosystem standards
- Architecture: HIGH - Follows exact patterns used in Phases 4-6 (service layer, queued jobs, composables, MapLibre)
- Pitfalls: HIGH - Identified from direct codebase inspection (DomPDF limitations, MapLibre v5 API change, PostGIS patterns)
- KPI computation: HIGH - Incident model has all necessary timestamps (dispatched_at, on_scene_at, resolved_at, outcome)
- Report generation: HIGH - DomPDF + queued jobs pattern already proven in GenerateIncidentReport

**Research date:** 2026-03-13
**Valid until:** 2026-04-13 (stable stack, no fast-moving dependencies)
