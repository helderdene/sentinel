<script setup lang="ts">
import mapboxgl from 'mapbox-gl';
import type { GeoJSONSource } from 'mapbox-gl';
import { computed, onMounted, onUnmounted, ref, shallowRef, watch } from 'vue';
import { useDirections, type DirectionsStep } from '@/composables/useDirections';
import { useSpeech } from '@/composables/useSpeech';
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

const { getRoute } = useDirections();
const speech = useSpeech();
const spokenCues = new Set<string>();

const mapContainer = ref<HTMLDivElement | null>(null);
const map = shallowRef<mapboxgl.Map | null>(null);
const isMapReady = ref(false);
const routeDistanceKm = ref<number | null>(null);
const routeEtaMin = ref<number | null>(null);
const routeSteps = ref<DirectionsStep[]>([]);
const currentStepIndex = ref(0);

const isNavMode = computed(() => props.incident.status === 'EN_ROUTE');

function haversineMeters(
    a: [number, number],
    b: [number, number],
): number {
    const R = 6371000;
    const dLat = ((b[1] - a[1]) * Math.PI) / 180;
    const dLng = ((b[0] - a[0]) * Math.PI) / 180;
    const h =
        Math.sin(dLat / 2) ** 2 +
        Math.cos((a[1] * Math.PI) / 180) *
            Math.cos((b[1] * Math.PI) / 180) *
            Math.sin(dLng / 2) ** 2;

    return R * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h));
}

function bearingBetween(
    a: [number, number],
    b: [number, number],
): number {
    const lat1 = (a[1] * Math.PI) / 180;
    const lat2 = (b[1] * Math.PI) / 180;
    const dLng = ((b[0] - a[0]) * Math.PI) / 180;
    const y = Math.sin(dLng) * Math.cos(lat2);
    const x =
        Math.cos(lat1) * Math.sin(lat2) -
        Math.sin(lat1) * Math.cos(lat2) * Math.cos(dLng);

    return (Math.atan2(y, x) * 180) / Math.PI;
}

const currentStep = computed<DirectionsStep | null>(() => {
    const idx = Math.min(currentStepIndex.value, routeSteps.value.length - 1);

    return routeSteps.value[idx] ?? null;
});

const distanceToNextTurnMeters = computed<number | null>(() => {
    if (!props.gpsPosition) {
        return null;
    }

    const next = routeSteps.value[currentStepIndex.value + 1];

    if (!next) {
        return null;
    }

    return haversineMeters(
        [props.gpsPosition.lng, props.gpsPosition.lat],
        next.location,
    );
});

const maneuverGlyph = computed(() => {
    const step = currentStep.value;

    if (!step) {
        return '↑';
    }

    const modifier = step.modifier ?? '';
    const type = step.type;

    if (type === 'arrive') {
        return '●';
    }

    if (type === 'roundabout' || type === 'rotary') {
        return '↻';
    }

    switch (modifier) {
        case 'sharp right':
            return '↱';
        case 'right':
            return '→';
        case 'slight right':
            return '↗';
        case 'sharp left':
            return '↰';
        case 'left':
            return '←';
        case 'slight left':
            return '↖';
        case 'uturn':
            return '↺';
        default:
            return '↑';
    }
});

function formatDistance(meters: number | null): string {
    if (meters === null) {
        return '';
    }

    if (meters < 50) {
        return 'Now';
    }

    if (meters < 1000) {
        return `${Math.round(meters / 10) * 10} m`;
    }

    return `${(meters / 1000).toFixed(1)} km`;
}

const incidentCoords = computed(() => {
    const c = props.incident.coordinates;

    if (!c || !Number.isFinite(c.lat) || !Number.isFinite(c.lng)) {
        return null;
    }

    return { lng: c.lng, lat: c.lat };
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
    routeSteps.value = route.steps ?? [];
    currentStepIndex.value = 0;
    spokenCues.clear();

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
            projection: 'mercator',
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
        layout: {
            'line-cap': 'round',
            'line-join': 'round',
        },
        paint: {
            'line-color': routeColor,
            'line-width': 14,
            'line-opacity': 0.35,
            'line-blur': 6,
        },
    });

    map.value.addLayer({
        id: 'route-line-layer',
        type: 'line',
        source: 'route-line',
        layout: {
            'line-cap': 'round',
            'line-join': 'round',
        },
        paint: {
            'line-color': routeColor,
            'line-width': 6,
            'line-opacity': 0.95,
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

        if (isNavMode.value) {
            recenterNavCamera();
        } else {
            fitBounds();
        }

        fetchRouteGeometry();
    }
}

function recenterNavCamera(): void {
    if (!map.value || !props.gpsPosition) {
        return;
    }

    const gps: [number, number] = [props.gpsPosition.lng, props.gpsPosition.lat];

    let bearing = 0;
    const next = routeSteps.value[currentStepIndex.value + 1];

    if (next) {
        bearing = bearingBetween(gps, next.location);
    } else if (incidentCoords.value) {
        bearing = bearingBetween(gps, [
            incidentCoords.value.lng,
            incidentCoords.value.lat,
        ]);
    }

    map.value.easeTo({
        center: gps,
        zoom: 17,
        pitch: 60,
        bearing,
        duration: 600,
    });
}

function advanceCurrentStep(): void {
    if (!props.gpsPosition || routeSteps.value.length === 0) {
        return;
    }

    const gps: [number, number] = [props.gpsPosition.lng, props.gpsPosition.lat];
    const next = routeSteps.value[currentStepIndex.value + 1];

    if (!next) {
        return;
    }

    if (haversineMeters(gps, next.location) < 25) {
        currentStepIndex.value += 1;
    }
}

let startAnnouncementPending = false;

function buildStartAnnouncement(): string | null {
    const typeName = props.incident.incident_type?.name;
    const locationText =
        props.incident.location_text ?? props.incident.barangay?.name ?? null;
    const km = routeDistanceKm.value;

    if (!typeName && !locationText && km === null) {
        return null;
    }

    const parts: string[] = ['Starting route'];

    if (typeName) {
        parts.push(`to ${typeName}`);
    }

    if (locationText) {
        parts.push(`at ${locationText}`);
    }

    if (km !== null && Number.isFinite(km) && km > 0) {
        const distanceText =
            km < 1
                ? `${Math.round(km * 1000)} meters away`
                : `${km.toFixed(1)} kilometers away`;
        parts.push(distanceText);
    }

    const prefix = parts.join(' ') + '.';

    const next = routeSteps.value[currentStepIndex.value + 1];
    const firstCue = next?.voice_instructions?.[0]?.announcement;
    const firstInstruction = firstCue ?? currentStep.value?.instruction ?? '';

    if (firstCue && next) {
        spokenCues.add(
            `${currentStepIndex.value + 1}:${next.voice_instructions?.[0]?.distance_along_geometry ?? 0}`,
        );
    }

    return firstInstruction ? `${prefix} ${firstInstruction}` : prefix;
}

function speakStartOfRoute(): void {
    if (!isNavMode.value) {
        return;
    }

    // Route distance may still be loading when EN_ROUTE is entered; defer
    // the full announcement until it arrives.
    if (routeDistanceKm.value === null) {
        startAnnouncementPending = true;

        return;
    }

    const announcement = buildStartAnnouncement();

    if (announcement) {
        speech.speak(announcement);
    }

    startAnnouncementPending = false;
}

watch(routeDistanceKm, (km) => {
    if (startAnnouncementPending && km !== null && isNavMode.value) {
        speakStartOfRoute();
    }
});

function maybeSpeakVoiceCue(): void {
    if (!isNavMode.value || !props.gpsPosition) {
        return;
    }

    const nextStep = routeSteps.value[currentStepIndex.value + 1];

    if (!nextStep?.voice_instructions?.length) {
        return;
    }

    const gps: [number, number] = [props.gpsPosition.lng, props.gpsPosition.lat];
    const distanceToManeuver = haversineMeters(gps, nextStep.location);

    // Voice cues are sorted by distance_along_geometry descending in Mapbox's
    // response (furthest-away announcement first). Fire the first cue whose
    // threshold we've crossed and that hasn't fired yet.
    for (const cue of nextStep.voice_instructions) {
        const cueKey = `${currentStepIndex.value + 1}:${cue.distance_along_geometry}`;

        if (spokenCues.has(cueKey)) {
            continue;
        }

        if (distanceToManeuver <= cue.distance_along_geometry) {
            spokenCues.add(cueKey);
            speech.speak(cue.announcement);
            break;
        }
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
            advanceCurrentStep();
            maybeSpeakVoiceCue();
            updateUnitPosition();
            tryFirstFit();
        }
    },
    { deep: true, immediate: true },
);

watch(currentStepIndex, () => {
    // New step became current — allow its cues to fire again
    spokenCues.clear();
});

watch(isNavMode, (navActive) => {
    if (!map.value || !isMapReady.value) {
        return;
    }

    if (navActive) {
        recenterNavCamera();
        speakStartOfRoute();
    } else {
        startAnnouncementPending = false;
        speech.cancel();
        map.value.easeTo({ pitch: 0, bearing: 0, duration: 400 });
        fitBounds();
    }
});

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
    speech.cancel();
    map.value?.remove();
});
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
        <!-- Turn-by-turn banner: shown only while EN_ROUTE -->
        <div
            v-if="isNavMode && currentStep"
            class="shrink-0 px-3 pt-3 pb-2"
        >
            <div
                class="flex items-center gap-3 rounded-[13px] bg-[#05101E] px-4 py-3 text-white shadow-[0_6px_20px_rgba(0,0,0,0.35)]"
            >
                <div
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-t-accent text-2xl font-bold"
                    aria-hidden="true"
                >
                    {{ maneuverGlyph }}
                </div>

                <div class="min-w-0 flex-1">
                    <p
                        v-if="distanceToNextTurnMeters !== null"
                        class="font-mono text-[11px] tracking-[1.5px] text-t-accent uppercase"
                    >
                        In {{ formatDistance(distanceToNextTurnMeters) }}
                    </p>
                    <p class="truncate text-[14px] font-semibold">
                        {{
                            currentStep.instruction ||
                            'Continue to destination'
                        }}
                    </p>
                </div>

                <button
                    v-if="speech.isSupported.value"
                    type="button"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/10 transition-colors hover:bg-white/20 active:scale-95"
                    :aria-label="
                        speech.isEnabled.value
                            ? 'Mute voice directions'
                            : 'Unmute voice directions'
                    "
                    :aria-pressed="speech.isEnabled.value"
                    @click="speech.toggle()"
                >
                    <svg
                        v-if="speech.isEnabled.value"
                        width="18"
                        height="18"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5" />
                        <path d="M15.54 8.46a5 5 0 0 1 0 7.07" />
                        <path d="M19.07 4.93a10 10 0 0 1 0 14.14" />
                    </svg>
                    <svg
                        v-else
                        width="18"
                        height="18"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5" />
                        <line x1="23" y1="9" x2="17" y2="15" />
                        <line x1="17" y1="9" x2="23" y2="15" />
                    </svg>
                </button>
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
