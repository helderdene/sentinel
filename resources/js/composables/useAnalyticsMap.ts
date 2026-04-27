import mapboxgl from 'mapbox-gl';
import type { GeoJSONSource, MapboxGeoJSONFeature } from 'mapbox-gl';
import { onUnmounted, ref, shallowRef } from 'vue';
import type { Ref } from 'vue';

import { barangayDetail } from '@/actions/App/Http/Controllers/AnalyticsController';
import type { BarangayDensity, BarangayDetail } from '@/types/analytics';

const BUTUAN_CENTER: [number, number] = [125.5406, 8.9475];
const BUTUAN_ZOOM = 12;

const BLANK_STYLE: mapboxgl.StyleSpecification = {
    version: 8,
    sources: {},
    layers: [],
};

const DENSITY_COLORS: Array<[number, string]> = [
    [0, '#E8F4FD'],
    [5, '#A8D4F0'],
    [15, '#6AB4E3'],
    [30, '#378ADD'],
    [50, '#185FA5'],
];

export function useAnalyticsMap(
    containerRef: Ref<HTMLElement | null>,
    geojson: GeoJSON.FeatureCollection,
    densityData: BarangayDensity[],
) {
    const map = shallowRef<mapboxgl.Map | null>(null);
    const isLoaded = ref(false);

    let hoverPopup: mapboxgl.Popup | null = null;
    let detailPopup: mapboxgl.Popup | null = null;
    let hoveredFeatureId: number | string | null = null;
    let mergedGeojson: GeoJSON.FeatureCollection;

    function mergeDensityIntoGeojson(
        gj: GeoJSON.FeatureCollection,
        density: BarangayDensity[],
    ): GeoJSON.FeatureCollection {
        const densityMap = new Map<number, number>();

        density.forEach((d) => {
            densityMap.set(d.barangay_id, d.incident_count);
        });

        return {
            type: 'FeatureCollection',
            features: gj.features.map((feature) => ({
                ...feature,
                properties: {
                    ...feature.properties,
                    incident_count:
                        densityMap.get(feature.properties?.id ?? feature.id) ??
                        0,
                },
            })),
        };
    }

    function addSourceAndLayers(): void {
        if (!map.value) {
            return;
        }

        map.value.addSource('barangays', {
            type: 'geojson',
            data: mergedGeojson,
            promoteId: 'id',
        });

        map.value.addLayer({
            id: 'barangay-fill',
            type: 'fill',
            source: 'barangays',
            paint: {
                'fill-color': [
                    'interpolate',
                    ['linear'],
                    ['get', 'incident_count'],
                    ...DENSITY_COLORS.flatMap(([val, color]) => [val, color]),
                ],
                'fill-opacity': [
                    'case',
                    ['boolean', ['feature-state', 'hover'], false],
                    0.9,
                    0.7,
                ],
            },
        });

        map.value.addLayer({
            id: 'barangay-outline',
            type: 'line',
            source: 'barangays',
            paint: {
                'line-color': '#94a3b8',
                'line-width': 1,
            },
        });
    }

    function addInteractionHandlers(): void {
        if (!map.value) {
            return;
        }

        hoverPopup = new mapboxgl.Popup({
            closeButton: false,
            closeOnClick: false,
        });

        // Hover
        map.value.on('mousemove', 'barangay-fill', (e) => {
            if (!map.value || !e.features || e.features.length === 0) {
                return;
            }

            map.value.getCanvas().style.cursor = 'pointer';

            const feature = e.features[0] as MapboxGeoJSONFeature;
            const featureId = feature.properties?.id ?? feature.id;

            if (hoveredFeatureId !== null && hoveredFeatureId !== featureId) {
                map.value.setFeatureState(
                    { source: 'barangays', id: hoveredFeatureId },
                    { hover: false },
                );
            }

            hoveredFeatureId = featureId as number;
            map.value.setFeatureState(
                { source: 'barangays', id: hoveredFeatureId },
                { hover: true },
            );

            const name = feature.properties?.name ?? 'Unknown';
            const count = feature.properties?.incident_count ?? 0;

            hoverPopup
                ?.setLngLat(e.lngLat)
                .setHTML(
                    `<strong>${name}</strong><br>${count} incident${count !== 1 ? 's' : ''}`,
                )
                .addTo(map.value);
        });

        map.value.on('mouseleave', 'barangay-fill', () => {
            if (!map.value) {
                return;
            }

            map.value.getCanvas().style.cursor = '';

            if (hoveredFeatureId !== null) {
                map.value.setFeatureState(
                    { source: 'barangays', id: hoveredFeatureId },
                    { hover: false },
                );
                hoveredFeatureId = null;
            }

            hoverPopup?.remove();
        });

        // Click
        map.value.on('click', 'barangay-fill', async (e) => {
            if (!map.value || !e.features || e.features.length === 0) {
                return;
            }

            const feature = e.features[0] as MapboxGeoJSONFeature;
            const bgId = feature.properties?.id;

            if (!bgId) {
                return;
            }

            detailPopup?.remove();

            detailPopup = new mapboxgl.Popup({
                closeButton: true,
                closeOnClick: false,
                maxWidth: '280px',
            })
                .setLngLat(e.lngLat)
                .setHTML(
                    '<div class="p-2 text-sm text-neutral-600">Loading...</div>',
                )
                .addTo(map.value);

            try {
                const response = await fetch(barangayDetail.url(bgId));
                const data: BarangayDetail = await response.json();

                const topTypesHtml = data.top_types
                    .slice(0, 5)
                    .map(
                        (t) =>
                            `<li class="flex justify-between"><span>${t.name}</span><span class="font-mono">${t.count}</span></li>`,
                    )
                    .join('');

                const priorityHtml = Object.entries(data.priority_breakdown)
                    .map(
                        ([p, count]) =>
                            `<span class="inline-block rounded px-1.5 py-0.5 text-xs font-medium" style="background: ${getPriorityColor(p)}22; color: ${getPriorityColor(p)}">${p}: ${count}</span>`,
                    )
                    .join(' ');

                const html = `
                    <div class="min-w-[200px] p-1">
                        <h3 class="mb-1 text-sm font-semibold">${data.name}</h3>
                        <p class="mb-2 text-xs text-neutral-500">${data.total} total incidents</p>
                        <ul class="mb-2 space-y-0.5 text-xs">${topTypesHtml}</ul>
                        <div class="mb-2 flex flex-wrap gap-1">${priorityHtml}</div>
                        <a href="/analytics/dashboard?barangay_id=${bgId}"
                           class="inline-block rounded bg-blue-600 px-2 py-1 text-xs font-medium text-white hover:bg-blue-700">
                            Filter Dashboard
                        </a>
                    </div>
                `;

                detailPopup?.setHTML(html);
            } catch {
                detailPopup?.setHTML(
                    '<div class="p-2 text-sm text-red-600">Failed to load details</div>',
                );
            }
        });
    }

    function getPriorityColor(priority: string): string {
        const colors: Record<string, string> = {
            P1: '#E24B4A',
            P2: '#EF9F27',
            P3: '#1D9E75',
            P4: '#378ADD',
        };

        return colors[priority] ?? '#6b7280';
    }

    function init(): void {
        if (!containerRef.value) {
            return;
        }

        mergedGeojson = mergeDensityIntoGeojson(geojson, densityData);

        map.value = new mapboxgl.Map({
            container: containerRef.value,
            style: BLANK_STYLE,
            center: BUTUAN_CENTER,
            zoom: BUTUAN_ZOOM,
            preserveDrawingBuffer: true,
            projection: 'mercator',
        });

        map.value.addControl(new mapboxgl.NavigationControl(), 'top-left');

        map.value.on('load', () => {
            isLoaded.value = true;
            addSourceAndLayers();
            addInteractionHandlers();
        });
    }

    function updateDensity(newDensity: BarangayDensity[]): void {
        if (!map.value) {
            return;
        }

        mergedGeojson = mergeDensityIntoGeojson(geojson, newDensity);

        const source = map.value.getSource('barangays') as
            | GeoJSONSource
            | undefined;

        source?.setData(mergedGeojson);
    }

    function exportPng(): void {
        if (!map.value) {
            return;
        }

        const canvas = map.value.getCanvas();
        const dataUrl = canvas.toDataURL('image/png');

        const link = document.createElement('a');
        link.download = `incident-heatmap-${new Date().toISOString().slice(0, 10)}.png`;
        link.href = dataUrl;
        link.click();
    }

    onUnmounted(() => {
        hoverPopup?.remove();
        detailPopup?.remove();
        map.value?.remove();
    });

    return {
        map,
        isLoaded,
        init,
        updateDensity,
        exportPng,
    };
}
