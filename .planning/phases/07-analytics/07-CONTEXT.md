# Phase 7: Analytics - Context

**Gathered:** 2026-03-13
**Status:** Ready for planning

<domain>
## Phase Boundary

Supervisors and admin users can view operational KPIs, identify incident hotspots on a choropleth heatmap, and generate compliance reports (DILG monthly, NDRRMC SitRep, quarterly, annual). Analytics is a read-only dashboard and reporting layer — it does not modify incident data or introduce new workflows.

</domain>

<decisions>
## Implementation Decisions

### KPI Dashboard Layout
- 5 metric cards in a responsive row: avg response time, avg scene arrival time, resolution rate, unit utilization, false alarm rate
- Each card shows: current value, trend arrow (up/down with percentage), small sparkline chart for the selected period
- Below the cards, detailed line charts per KPI for the selected date range — users can toggle which KPIs to show
- Sticky horizontal filter bar pinned below the page header with: date range presets (7d / 30d / 90d / 365d / Custom date picker), incident type dropdown, priority selector, barangay dropdown
- Default filter: last 30 days, all types, all priorities, all barangays

### Heatmap Visualization
- Choropleth map using MapLibre GL JS with barangay polygons colored by incident density (light-to-dark gradient)
- Hover tooltip shows barangay name and incident count
- Click popup shows: barangay name, total incidents, top 5 incident types breakdown, priority breakdown, link to filter KPI dashboard for that barangay
- PNG export via "Export" button that captures MapLibre canvas with legend and filter labels included
- Shares the same filter bar as the KPI dashboard — filters apply across both views

### Report Generation
- Central "Reports" download center page listing all generated reports with date, type, and download links (PDF + CSV where applicable)
- **DILG monthly**: Scheduled Laravel job on 1st of each month generates PDF + CSV aggregating incidents by type, priority, barangay, outcome. Stored in reports download center.
- **NDRRMC SitRep**: Auto-generated on P1 incident closure. PDF stored in download center AND logged as timeline entry on the incident with link to PDF. Stubbed XML submission via Phase 6 NDRRMC stub service triggered in background.
- **Quarterly performance**: On-demand from reports page. User selects period (Q1 2026, etc.), generates in background, notification when ready. Content: KPI trend lines over quarter, incident volume bar chart by week, top 10 barangays by incident count, breakdown by type/priority, comparison to previous quarter.
- **Annual summary**: On-demand from reports page. User selects year, generates in background. Year-over-year comparison for Mayor's Office.

### Analytics Navigation
- Dedicated /analytics route with tab bar: Dashboard, Heatmap, Reports
- Sidebar link "Analytics" in the main AppLayout sidebar — visible to supervisor and admin roles only
- Dispatchers, operators, and responders do NOT see the analytics sidebar link
- Shared filter state across all three tabs — changing filters on Dashboard carries over to Heatmap
- Filter state stored in URL query params for shareable/bookmarkable URLs

### Claude's Discretion
- Sparkline chart library choice (lightweight inline charts)
- Line chart library for detailed KPI trends (Chart.js, ApexCharts, or similar)
- Choropleth color scale (sequential single-hue or multi-hue)
- PDF layout and styling for compliance reports
- Report storage path and cleanup strategy
- Loading states and skeleton designs
- Exact tab component implementation

</decisions>

<specifics>
## Specific Ideas

- KPI metric cards follow the design system (DM Sans + Space Mono, color tokens) established in Phase 8
- Dispatch console's useDispatchSession composable already computes live metrics — similar pattern for analytics but querying historical data from backend
- MapLibre choropleth uses existing Barangay PostGIS boundary polygons (86 barangays seeded in Phase 1)
- DomPDF already installed and used for incident closure reports (Phase 5) — reuse for compliance PDFs
- NDRRMC stub service (StubNdrrmcReportService from Phase 6) handles the XML submission side

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `useDispatchSession` composable: Pattern for computing KPI metrics from incident data (active incidents, average handle time, unit utilization)
- `DispatchLayout` / `IntakeLayout`: Full-screen layout patterns with provide/inject bridge
- MapLibre GL JS setup in dispatch console: Map initialization, WebGL layers, dark/light style switching
- DomPDF via `GenerateIncidentReport` job: PDF generation pattern with queued background jobs
- `StubNdrrmcReportService`: NDRRMC SitRep XML generation with SimpleXMLElement
- Barangay model with PostGIS boundary polygons and incidents() relationship
- IncidentOutcome enum with FalseAlarm case for false alarm rate calculation
- Design tokens (CSS custom properties, color-mix()) from Phase 8

### Established Patterns
- Service layer: Contracts/ for interfaces, Services/ for implementations, bound in AppServiceProvider
- Inertia page with AppLayout sidebar for role-gated navigation
- Wayfinder for type-safe route generation from controllers
- Form Request classes for validation, deferred props for role-gated data
- Scheduled jobs registered in routes/console.php
- URL query params synced with component state for bookmarkable views

### Integration Points
- Sidebar navigation in AppSidebar.vue: Add "Analytics" link for supervisor/admin roles
- routes/web.php: New analytics route group with role middleware
- Incident model: Query lifecycle timestamps for KPI calculation
- Barangay model: ST_Contains for choropleth, incidents() relationship for density
- IncidentType model: Group incidents by type for report breakdowns
- Phase 6 NDRRMC stub: Wire SitRep auto-generation on P1 closure
- Horizon queue: Background job processing for report generation

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 07-analytics*
*Context gathered: 2026-03-13*
