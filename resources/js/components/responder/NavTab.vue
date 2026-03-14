<script setup lang="ts">
import mapboxgl from 'mapbox-gl';
import type { GeoJSONSource } from 'mapbox-gl';
import { computed, onMounted, onUnmounted, ref, shallowRef, watch } from 'vue';
import { useOsrmRoute } from '@/composables/useOsrmRoute';
import type { ResponderIncident } from '@/types/responder';

const MAP_STYLE = 'mapbox://styles/helderdene/cmmq06eqr005j01skbwodfq08';

// --- Icon generation (matches dispatch console) ---
const ICON_SIZE = 64;

const INCIDENT_ICON_PATH =
    'M12 5.5c-.38 0-.73.2-.92.53l-4.86 8.4c-.19.33-.19.74 0 1.07.19.34.54.54.92.54h9.72c.38 0 .73-.2.92-.54.19-.33.19-.74 0-1.07l-4.86-8.4A1.06 1.06 0 0 0 12 5.5zm.5 8.5a.75.75 0 1 1-1 0 .75.75 0 0 1 1 0zM12 12a.5.5 0 0 1-.5-.5v-2a.5.5 0 1 1 1 0v2a.5.5 0 0 1-.5.5z';

const UNIT_ICON_PATH =
    'M18 9.5h-2V7H6.5c-.83 0-1.5.68-1.5 1.5v6.5h1.5c0 1.1.9 2 2 2s2-.9 2-2h3c0 1.1.9 2 2 2s2-.9 2-2H19v-3.5l-1-2zm-9.5 7.5c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm9-6.5 1.36 1.75H16V10.5h1.5zM15.5 17c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1z';

const PRIORITY_COLORS: Record<string, string> = {
    P1: '#E24B4A',
    P2: '#EF9F27',
    P3: '#1D9E75',
    P4: '#378ADD',
};

function buildCircleIconSvg(iconPath: string, color: string): string {
    return [
        `<svg xmlns="http://www.w3.org/2000/svg" width="${ICON_SIZE}" height="${ICON_SIZE}" viewBox="0 0 24 24">`,
        `<circle cx="12" cy="12" r="11.5" fill="${color}"/>`,
        `<circle cx="12" cy="12" r="10" fill="white"/>`,
        `<path d="${iconPath}" fill="${color}"/>`,
        '</svg>',
    ].join('');
}

function loadSvgAsImage(svg: string): Promise<HTMLImageElement> {
    const blob = new Blob([svg], { type: 'image/svg+xml' });
    const url = URL.createObjectURL(blob);

    return new Promise((resolve) => {
        const img = new Image(ICON_SIZE, ICON_SIZE);
        img.onload = () => {
            URL.revokeObjectURL(url);
            resolve(img);
        };
        img.src = url;
    });
}

const props = defineProps<{
    incident: ResponderIncident;
    gpsPosition: { lat: number; lng: number } | null;
    unitCallsign: string;
}>();

const { getRoute } = useOsrmRoute();

const mapContainer = ref<HTMLDivElement | null>(null);
const map = shallowRef<mapboxgl.Map | null>(null);
const isMapReady = ref(false);
const routeDistanceKm = ref<number | null>(null);
const routeEtaMin = ref<number | null>(null);

const incidentCoords = computed(() => {
    const c = props.incident.coordinates;

    if (!c || !Number.isFinite(c.lat) || !Number.isFinite(c.lng)) {
        return null;
    }

    return { lng: c.lng, lat: c.lat };
});

const googleMapsUrl = computed(() => {
    if (!incidentCoords.value) {
        return null;
    }

    return `https://www.google.com/maps/dir/?api=1&destination=${incidentCoords.value.lat},${incidentCoords.value.lng}&travelmode=driving`;
});

const distanceKm = computed(() => routeDistanceKm.value);

const etaMinutes = computed(() => routeEtaMin.value);

async function fetchRouteGeometry(): Promise<void> {
    if (!props.gpsPosition || !incidentCoords.value) {
        return;
    }

    const from: [number, number] = [
        props.gpsPosition.lng,
        props.gpsPosition.lat,
    ];
    const to: [number, number] = [
        incidentCoords.value.lng,
        incidentCoords.value.lat,
    ];
    const route = await getRoute(from, to);

    routeDistanceKm.value = route.distanceKm;
    routeEtaMin.value = route.durationMin;

    if (!map.value || !isMapReady.value) {
        return;
    }

    const lineSource = map.value.getSource('route-line') as
        | GeoJSONSource
        | undefined;

    if (lineSource) {
        lineSource.setData({
            type: 'Feature',
            geometry: {
                type: 'LineString',
                coordinates: route.coordinates,
            },
            properties: {},
        });
    }
}

function initMap(): void {
    if (!mapContainer.value || !incidentCoords.value) {
        return;
    }

    const center: [number, number] = props.gpsPosition
        ? [
              (props.gpsPosition.lng + incidentCoords.value.lng) / 2,
              (props.gpsPosition.lat + incidentCoords.value.lat) / 2,
          ]
        : [incidentCoords.value.lng, incidentCoords.value.lat];

    try {
        map.value = new mapboxgl.Map({
            container: mapContainer.value,
            style: MAP_STYLE,
            center,
            zoom: 13,
            pitch: 0,
        });

        map.value.addControl(
            new mapboxgl.NavigationControl({ visualizePitch: true }),
            'top-right',
        );

        map.value.on('load', async () => {
            map.value?.resize();

            // Load dispatch-style icons
            const priority = props.incident.priority ?? 'P2';
            const incidentColor = PRIORITY_COLORS[priority] ?? '#EF9F27';
            const [incidentImg, unitImg] = await Promise.all([
                loadSvgAsImage(
                    buildCircleIconSvg(INCIDENT_ICON_PATH, incidentColor),
                ),
                loadSvgAsImage(buildCircleIconSvg(UNIT_ICON_PATH, '#378ADD')),
            ]);
            map.value?.addImage('incident-icon', incidentImg);
            map.value?.addImage('unit-icon', unitImg);

            isMapReady.value = true;
            addMapSources();
            addMapLayers();

            if (props.gpsPosition && incidentCoords.value) {
                fitBounds();
                fetchRouteGeometry();
            }
        });
    } catch {
        // Mapbox init failure — container may not support WebGL
    }
}

function addMapSources(): void {
    if (!map.value || !incidentCoords.value) {
        return;
    }

    map.value.addSource('incident-point', {
        type: 'geojson',
        data: {
            type: 'Feature',
            geometry: {
                type: 'Point',
                coordinates: [
                    incidentCoords.value.lng,
                    incidentCoords.value.lat,
                ],
            },
            properties: {},
        },
    });

    map.value.addSource('unit-point', {
        type: 'geojson',
        data: props.gpsPosition
            ? {
                  type: 'Feature',
                  geometry: {
                      type: 'Point',
                      coordinates: [
                          props.gpsPosition.lng,
                          props.gpsPosition.lat,
                      ],
                  },
                  properties: {},
              }
            : { type: 'FeatureCollection', features: [] },
    });

    const lineCoords: [number, number][] = props.gpsPosition
        ? [
              [props.gpsPosition.lng, props.gpsPosition.lat],
              [incidentCoords.value.lng, incidentCoords.value.lat],
          ]
        : [];

    map.value.addSource('route-line', {
        type: 'geojson',
        data:
            lineCoords.length >= 2
                ? {
                      type: 'Feature',
                      geometry: {
                          type: 'LineString',
                          coordinates: lineCoords,
                      },
                      properties: {},
                  }
                : { type: 'FeatureCollection', features: [] },
    });
}

function addMapLayers(): void {
    if (!map.value) {
        return;
    }

    const routeColor =
        PRIORITY_COLORS[props.incident.priority ?? 'P2'] ?? '#EF9F27';

    map.value.addLayer({
        id: 'route-glow',
        type: 'line',
        source: 'route-line',
        paint: {
            'line-color': routeColor,
            'line-width': 8,
            'line-opacity': 0.25,
            'line-blur': 4,
        },
    });

    map.value.addLayer({
        id: 'route-line-layer',
        type: 'line',
        source: 'route-line',
        paint: {
            'line-color': routeColor,
            'line-width': 3,
            'line-dasharray': [2, 3],
        },
    });

    map.value.addLayer({
        id: 'incident-halo',
        type: 'circle',
        source: 'incident-point',
        paint: {
            'circle-radius': 20,
            'circle-color':
                PRIORITY_COLORS[props.incident.priority ?? 'P2'] ?? '#EF9F27',
            'circle-opacity': 0.15,
            'circle-blur': 1,
        },
    });

    map.value.addLayer({
        id: 'incident-icon',
        type: 'symbol',
        source: 'incident-point',
        layout: {
            'icon-image': 'incident-icon',
            'icon-size': 0.55,
            'icon-allow-overlap': true,
            'icon-ignore-placement': true,
            'icon-anchor': 'center',
        },
    });

    map.value.addLayer({
        id: 'unit-glow',
        type: 'circle',
        source: 'unit-point',
        paint: {
            'circle-radius': 18,
            'circle-color': '#378ADD',
            'circle-opacity': 0.15,
            'circle-blur': 1,
        },
    });

    map.value.addLayer({
        id: 'unit-icon',
        type: 'symbol',
        source: 'unit-point',
        layout: {
            'icon-image': 'unit-icon',
            'icon-size': 0.5,
            'icon-allow-overlap': true,
            'icon-ignore-placement': true,
            'icon-anchor': 'center',
        },
    });
}

function fitBounds(): void {
    if (!map.value || !incidentCoords.value) {
        return;
    }

    if (props.gpsPosition) {
        const bounds = new mapboxgl.LngLatBounds();

        bounds.extend([props.gpsPosition.lng, props.gpsPosition.lat]);
        bounds.extend([incidentCoords.value.lng, incidentCoords.value.lat]);

        map.value.fitBounds(bounds, {
            padding: 60,
            maxZoom: 16,
            duration: 500,
        });
    } else {
        map.value.flyTo({
            center: [incidentCoords.value.lng, incidentCoords.value.lat],
            zoom: 15,
            duration: 500,
        });
    }
}

function updateUnitPosition(): void {
    if (!map.value || !isMapReady.value || !props.gpsPosition) {
        return;
    }

    const unitSource = map.value.getSource('unit-point') as
        | GeoJSONSource
        | undefined;

    if (unitSource) {
        unitSource.setData({
            type: 'Feature',
            geometry: {
                type: 'Point',
                coordinates: [props.gpsPosition.lng, props.gpsPosition.lat],
            },
            properties: {},
        });
    }

    if (incidentCoords.value) {
        // Show a straight line immediately while OSRM loads
        const lineSource = map.value.getSource('route-line') as
            | GeoJSONSource
            | undefined;

        if (lineSource) {
            lineSource.setData({
                type: 'Feature',
                geometry: {
                    type: 'LineString',
                    coordinates: [
                        [props.gpsPosition.lng, props.gpsPosition.lat],
                        [incidentCoords.value.lng, incidentCoords.value.lat],
                    ],
                },
                properties: {},
            });
        }

        fitBounds();
        fetchRouteGeometry();
    }
}

let hasFittedBounds = false;

function tryFirstFit(): void {
    if (
        !hasFittedBounds &&
        props.gpsPosition &&
        incidentCoords.value &&
        isMapReady.value
    ) {
        hasFittedBounds = true;
        updateUnitPosition();
    }
}

watch(
    () => props.gpsPosition,
    (newVal) => {
        if (newVal) {
            updateUnitPosition();
            tryFirstFit();
        }
    },
    { deep: true, immediate: true },
);

// When map becomes ready, apply GPS if it arrived earlier
watch(isMapReady, (ready) => {
    if (ready) {
        tryFirstFit();
    }
});

onMounted(() => {
    initMap();
});

watch(incidentCoords, (coords) => {
    if (coords && !map.value) {
        initMap();
    }
});

onUnmounted(() => {
    map.value?.remove();
});
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
        <div class="shrink-0 px-4 pt-3 pb-2">
            <a
                v-if="googleMapsUrl"
                :href="googleMapsUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="flex min-h-[52px] w-full items-center justify-center gap-2 rounded-[13px] bg-t-accent font-sans text-[14px] font-bold tracking-wide text-white transition-transform active:scale-[0.98]"
                style="box-shadow: 0 6px 20px rgba(55, 138, 221, 0.31)"
            >
                <svg
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <polygon points="3 11 22 2 13 21 11 13 3 11" />
                </svg>
                OPEN IN GOOGLE MAPS
            </a>
            <div
                v-else
                class="flex min-h-[52px] w-full items-center justify-center rounded-[10px] bg-t-surface text-[13px] text-t-text-dim"
            >
                No coordinates available
            </div>
        </div>

        <div class="relative min-h-0 flex-1">
            <div
                v-if="gpsPosition === null && !incidentCoords"
                class="flex h-full items-center justify-center p-6"
            >
                <div
                    class="rounded-[10px] border border-t-border bg-t-surface p-6 text-center shadow-[0_1px_4px_rgba(0,0,0,.04)]"
                >
                    <p class="text-sm text-t-text-dim">GPS unavailable</p>
                    <p class="mt-1 text-xs text-t-text-faint">
                        Enable location services to see the map
                    </p>
                </div>
            </div>

            <div
                ref="mapContainer"
                style="position: absolute; inset: 0"
                :class="gpsPosition === null && !incidentCoords ? 'hidden' : ''"
            />

            <div
                v-if="etaMinutes !== null"
                class="absolute right-3 bottom-3 rounded-[10px] bg-[#05101E]/85 px-3 py-1.5 shadow-md backdrop-blur-sm"
            >
                <p class="font-mono text-[11px] font-bold text-white">
                    ETA: {{ etaMinutes }} min
                </p>
            </div>

            <div
                v-if="distanceKm !== null"
                class="absolute bottom-3 left-3 rounded-[10px] bg-[#05101E]/85 px-3 py-1.5 shadow-md backdrop-blur-sm"
            >
                <p class="font-mono text-[11px] text-t-text-dim">
                    {{ distanceKm.toFixed(1) }} km
                </p>
            </div>
        </div>
    </div>
</template>
