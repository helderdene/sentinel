<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import mapboxgl from 'mapbox-gl';
import type { GeoJSONSource } from 'mapbox-gl';
import { computed, onMounted, onUnmounted, ref, shallowRef, watch } from 'vue';
import { logout } from '@/routes';
import type { ResponderUnit } from '@/types/responder';

const MAP_STYLE = 'mapbox://styles/helderdene/cmmq06eqr005j01skbwodfq08';

const props = defineProps<{
    unit: ResponderUnit;
    connectionStatus: string;
    gpsPosition: { lat: number; lng: number } | null;
}>();

const displayPosition = computed(
    () => props.gpsPosition ?? props.unit.coordinates,
);

// --- Icon generation (matches dispatch console) ---
const ICON_SIZE = 64;
const UNIT_ICON_PATH =
    'M18 9.5h-2V7H6.5c-.83 0-1.5.68-1.5 1.5v6.5h1.5c0 1.1.9 2 2 2s2-.9 2-2h3c0 1.1.9 2 2 2s2-.9 2-2H19v-3.5l-1-2zm-9.5 7.5c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm9-6.5 1.36 1.75H16V10.5h1.5zM15.5 17c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1z';
const UNIT_COLOR = '#1D9E75';

function buildUnitIconSvg(): string {
    return [
        `<svg xmlns="http://www.w3.org/2000/svg" width="${ICON_SIZE}" height="${ICON_SIZE}" viewBox="0 0 24 24">`,
        `<circle cx="12" cy="12" r="11.5" fill="${UNIT_COLOR}"/>`,
        `<circle cx="12" cy="12" r="10" fill="white"/>`,
        `<path d="${UNIT_ICON_PATH}" fill="${UNIT_COLOR}"/>`,
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

const showLogoutConfirm = ref(false);

function handleLogout(): void {
    router.flushAll();
    router.post(logout.url());
}

const mapContainer = ref<HTMLDivElement | null>(null);
const map = shallowRef<mapboxgl.Map | null>(null);
const isMapReady = ref(false);

function initMap(): void {
    if (!mapContainer.value || !displayPosition.value) {
        return;
    }

    if (map.value) {
        return;
    }

    try {
        map.value = new mapboxgl.Map({
            container: mapContainer.value,
            style: MAP_STYLE,
            center: [displayPosition.value.lng, displayPosition.value.lat],
            zoom: 14,
            pitch: 0,
            interactive: true,
        });

        map.value.addControl(
            new mapboxgl.NavigationControl({ visualizePitch: true }),
            'top-right',
        );

        map.value.on('load', async () => {
            map.value?.resize();
            const img = await loadSvgAsImage(buildUnitIconSvg());
            map.value?.addImage('unit-available', img);
            isMapReady.value = true;
            addUnitSource();
            addUnitLayer();
        });
    } catch {
        // Map init failure
    }
}

function addUnitSource(): void {
    if (!map.value || !displayPosition.value) {
        return;
    }

    map.value.addSource('unit-point', {
        type: 'geojson',
        data: {
            type: 'Feature',
            geometry: {
                type: 'Point',
                coordinates: [
                    displayPosition.value.lng,
                    displayPosition.value.lat,
                ],
            },
            properties: {},
        },
    });
}

function addUnitLayer(): void {
    if (!map.value) {
        return;
    }

    map.value.addLayer({
        id: 'unit-glow',
        type: 'circle',
        source: 'unit-point',
        paint: {
            'circle-radius': 18,
            'circle-color': '#1D9E75',
            'circle-opacity': 0.15,
            'circle-blur': 1,
        },
    });

    map.value.addLayer({
        id: 'unit-icon',
        type: 'symbol',
        source: 'unit-point',
        layout: {
            'icon-image': 'unit-available',
            'icon-size': 0.5,
            'icon-allow-overlap': true,
            'icon-ignore-placement': true,
            'icon-anchor': 'center',
        },
    });
}

function updateUnitPosition(): void {
    if (!map.value || !isMapReady.value || !displayPosition.value) {
        return;
    }

    const source = map.value.getSource('unit-point') as
        | GeoJSONSource
        | undefined;

    if (source) {
        source.setData({
            type: 'Feature',
            geometry: {
                type: 'Point',
                coordinates: [
                    displayPosition.value.lng,
                    displayPosition.value.lat,
                ],
            },
            properties: {},
        });
    }

    map.value.easeTo({
        center: [displayPosition.value.lng, displayPosition.value.lat],
        duration: 1000,
    });
}

watch(displayPosition, updateUnitPosition, { deep: true });

watch(displayPosition, (pos) => {
    if (pos && !map.value) {
        initMap();
    }
});

onMounted(() => {
    initMap();
});

onUnmounted(() => {
    map.value?.remove();
});
</script>

<template>
    <div class="standby-map-wrapper relative">
        <div
            ref="mapContainer"
            style="
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                width: 100%;
                height: 100%;
            "
        />

        <!-- Overlay: callsign + status -->
        <div
            class="pointer-events-none absolute top-0 right-0 left-0 z-10 flex flex-col items-center gap-2 pt-4"
        >
            <div
                class="rounded-[10px] bg-[#05101E]/75 px-4 py-2 text-center backdrop-blur-sm"
            >
                <span class="font-mono text-[20px] font-extrabold text-white">
                    {{ unit.callsign }}
                </span>
            </div>

            <div
                class="flex items-center gap-2 rounded-full bg-[#05101E]/75 px-3 py-1.5 backdrop-blur-sm"
            >
                <span class="relative flex items-center justify-center">
                    <span class="standby-ring standby-ring--1" />
                    <span class="standby-ring standby-ring--2" />
                    <span
                        class="relative z-10 size-2.5 rounded-full"
                        :style="{
                            backgroundColor:
                                connectionStatus === 'online'
                                    ? 'var(--t-online)'
                                    : connectionStatus === 'reconnecting'
                                      ? 'var(--t-p3)'
                                      : 'var(--t-p1)',
                        }"
                    />
                </span>
                <span
                    class="font-mono text-[11px] font-semibold tracking-[1px] uppercase"
                    :class="
                        connectionStatus === 'online'
                            ? 'text-green-400'
                            : connectionStatus === 'reconnecting'
                              ? 'text-yellow-400'
                              : 'text-red-400'
                    "
                >
                    {{
                        connectionStatus === 'online'
                            ? 'Standing By'
                            : connectionStatus === 'reconnecting'
                              ? 'Reconnecting'
                              : 'Disconnected'
                    }}
                </span>
            </div>
        </div>

        <!-- GPS coordinates pill -->
        <div
            v-if="displayPosition"
            class="pointer-events-none absolute bottom-3 left-3 z-10 rounded-[10px] bg-[#05101E]/75 px-3 py-1.5 backdrop-blur-sm"
        >
            <span class="font-mono text-[11px] text-white/70">
                {{ displayPosition.lat.toFixed(5) }},
                {{ displayPosition.lng.toFixed(5) }}
            </span>
        </div>

        <div
            v-if="!displayPosition"
            class="pointer-events-none absolute bottom-3 left-3 z-10 rounded-[10px] bg-[#05101E]/75 px-3 py-1.5 backdrop-blur-sm"
        >
            <span class="text-[11px] text-white/50"> Acquiring GPS... </span>
        </div>

        <!-- Logout button -->
        <button
            type="button"
            class="absolute right-3 bottom-3 z-10 flex size-9 cursor-pointer items-center justify-center rounded-full bg-[#05101E]/75 backdrop-blur-sm transition-colors active:bg-[#05101E]/90"
            title="Log out"
            @click="showLogoutConfirm = true"
        >
            <svg
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="white"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                class="opacity-60"
            >
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                <polyline points="16 17 21 12 16 7" />
                <line x1="21" y1="12" x2="9" y2="12" />
            </svg>
        </button>

        <!-- Logout confirmation overlay -->
        <Teleport to="body">
            <Transition name="fade">
                <div
                    v-if="showLogoutConfirm"
                    class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 backdrop-blur-sm"
                    @click.self="showLogoutConfirm = false"
                >
                    <div
                        class="mx-6 w-full max-w-[280px] rounded-2xl bg-t-surface p-5 shadow-xl"
                    >
                        <p
                            class="text-center font-mono text-[14px] font-bold text-t-text"
                        >
                            Log out?
                        </p>
                        <p
                            class="mt-1.5 text-center text-[12px] text-t-text-dim"
                        >
                            You will be signed out of your responder session.
                        </p>
                        <div class="mt-4 flex gap-2.5">
                            <button
                                type="button"
                                class="flex-1 cursor-pointer rounded-xl border border-t-border bg-transparent py-2.5 text-[13px] font-semibold text-t-text transition-colors active:bg-t-bg"
                                @click="showLogoutConfirm = false"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                class="flex-1 cursor-pointer rounded-xl bg-t-p1 py-2.5 text-[13px] font-semibold text-white transition-colors active:bg-red-700"
                                @click="handleLogout"
                            >
                                Log out
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
.standby-map-wrapper {
    /* 44px topbar + 80px tabbar */
    height: calc(100dvh - 44px - 80px);
}

.standby-ring {
    position: absolute;
    border-radius: 50%;
    border: 1px solid var(--t-online);
    opacity: 0;
    animation: standby-pulse 3s ease-out infinite;
}

.standby-ring--1 {
    width: 40px;
    height: 40px;
    animation-delay: 0s;
}

.standby-ring--2 {
    width: 60px;
    height: 60px;
    animation-delay: 1s;
}

.standby-ring--3 {
    width: 80px;
    height: 80px;
    animation-delay: 2s;
}

@keyframes standby-pulse {
    0% {
        transform: scale(0.5);
        opacity: 0.5;
    }
    100% {
        transform: scale(1);
        opacity: 0;
    }
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
