<script setup lang="ts">
import maplibregl from 'maplibre-gl';
import type { GeoJSONSource, Map as MaplibreMap } from 'maplibre-gl';
import { computed, onMounted, onUnmounted, ref, shallowRef, watch } from 'vue';
import type { ResponderIncident } from '@/types/responder';

const MAP_STYLE: maplibregl.StyleSpecification = {
    version: 8,
    name: 'Light',
    sources: {
        'carto-light': {
            type: 'raster',
            tiles: [
                'https://a.basemaps.cartocdn.com/light_all/{z}/{x}/{y}@2x.png',
                'https://b.basemaps.cartocdn.com/light_all/{z}/{x}/{y}@2x.png',
                'https://c.basemaps.cartocdn.com/light_all/{z}/{x}/{y}@2x.png',
            ],
            tileSize: 256,
            attribution:
                '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a> &copy; <a href="https://carto.com/">CARTO</a>',
        },
    },
    layers: [
        {
            id: 'background',
            type: 'background',
            paint: { 'background-color': '#f2f3f0' },
        },
        {
            id: 'carto-light-layer',
            type: 'raster',
            source: 'carto-light',
            minzoom: 0,
            maxzoom: 20,
        },
    ],
};

const ETA_SPEED_KMH = 30;

const props = defineProps<{
    incident: ResponderIncident;
    gpsPosition: { lat: number; lng: number } | null;
    unitCallsign: string;
}>();

const mapContainer = ref<HTMLDivElement | null>(null);
const map = shallowRef<MaplibreMap | null>(null);
const isMapReady = ref(false);

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

const distanceKm = computed(() => {
    if (!props.gpsPosition || !incidentCoords.value) {
        return null;
    }

    const R = 6371;
    const dLat = toRad(incidentCoords.value.lat - props.gpsPosition.lat);
    const dLng = toRad(incidentCoords.value.lng - props.gpsPosition.lng);
    const a =
        Math.sin(dLat / 2) ** 2 +
        Math.cos(toRad(props.gpsPosition.lat)) *
            Math.cos(toRad(incidentCoords.value.lat)) *
            Math.sin(dLng / 2) ** 2;
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

    return R * c;
});

const etaMinutes = computed(() => {
    if (distanceKm.value === null) {
        return null;
    }

    return Math.max(1, Math.round((distanceKm.value / ETA_SPEED_KMH) * 60));
});

function toRad(deg: number): number {
    return (deg * Math.PI) / 180;
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
        map.value = new maplibregl.Map({
            container: mapContainer.value,
            style: MAP_STYLE,
            center,
            zoom: 13,
            maxPitch: 0,
            dragRotate: false,
        });

        map.value.on('load', () => {
            isMapReady.value = true;
            addMapSources();
            addMapLayers();
            fitBounds();
        });
    } catch {
        // MapLibre init failure — container may not support WebGL
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
        data: {
            type: 'Feature',
            geometry: {
                type: 'Point',
                coordinates: props.gpsPosition
                    ? [props.gpsPosition.lng, props.gpsPosition.lat]
                    : [incidentCoords.value.lng, incidentCoords.value.lat],
            },
            properties: {},
        },
    });

    const lineCoords: [number, number][] = [];

    if (props.gpsPosition) {
        lineCoords.push([props.gpsPosition.lng, props.gpsPosition.lat]);
    }

    lineCoords.push([incidentCoords.value.lng, incidentCoords.value.lat]);

    map.value.addSource('route-line', {
        type: 'geojson',
        data: {
            type: 'Feature',
            geometry: {
                type: 'LineString',
                coordinates: lineCoords,
            },
            properties: {},
        },
    });
}

function addMapLayers(): void {
    if (!map.value) {
        return;
    }

    map.value.addLayer({
        id: 'route-line-layer',
        type: 'line',
        source: 'route-line',
        paint: {
            'line-color': '#2563eb',
            'line-width': 2.5,
            'line-dasharray': [3, 3],
        },
    });

    map.value.addLayer({
        id: 'incident-pulse',
        type: 'circle',
        source: 'incident-point',
        paint: {
            'circle-radius': 18,
            'circle-color': '#dc2626',
            'circle-opacity': 0.2,
            'circle-blur': 0.8,
        },
    });

    map.value.addLayer({
        id: 'incident-core',
        type: 'circle',
        source: 'incident-point',
        paint: {
            'circle-radius': 8,
            'circle-color': '#dc2626',
            'circle-stroke-width': 2,
            'circle-stroke-color': '#ffffff',
        },
    });

    map.value.addLayer({
        id: 'unit-core',
        type: 'circle',
        source: 'unit-point',
        paint: {
            'circle-radius': 7,
            'circle-color': '#3b82f6',
            'circle-stroke-width': 2,
            'circle-stroke-color': '#ffffff',
        },
    });
}

function fitBounds(): void {
    if (!map.value || !incidentCoords.value) {
        return;
    }

    if (props.gpsPosition) {
        const bounds = new maplibregl.LngLatBounds();

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
    }
}

watch(() => props.gpsPosition, updateUnitPosition, { deep: true });

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
                style="box-shadow: 0 6px 20px rgba(37, 99, 235, 0.31)"
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
                class="absolute right-3 bottom-3 rounded-[10px] bg-[#0f172a]/85 px-3 py-1.5 shadow-md backdrop-blur-sm"
            >
                <p class="font-mono text-[11px] font-bold text-white">
                    ETA: {{ etaMinutes }} min
                </p>
            </div>

            <div
                v-if="distanceKm !== null"
                class="absolute bottom-3 left-3 rounded-[10px] bg-[#0f172a]/85 px-3 py-1.5 shadow-md backdrop-blur-sm"
            >
                <p class="font-mono text-[11px] text-t-text-dim">
                    {{ distanceKm.toFixed(1) }} km
                </p>
            </div>
        </div>
    </div>
</template>
