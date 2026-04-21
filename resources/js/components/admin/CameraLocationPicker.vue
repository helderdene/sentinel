<script setup lang="ts">
import { Search } from 'lucide-vue-next';
import mapboxgl from 'mapbox-gl';
import { onMounted, onUnmounted, ref, shallowRef, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { useGeocodingSearch } from '@/composables/useGeocodingSearch';

type Props = {
    latitude: number | null;
    longitude: number | null;
    address: string | null;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:coordinates': [lat: number, lng: number];
    'update:address': [address: string];
}>();

const BUTUAN_CENTER: [number, number] = [125.5406, 8.9475];
const BUTUAN_ZOOM = 13;
const PIN_COLOR = '#E24B4A';
const MAP_STYLE = 'mapbox://styles/helderdene/cmmq06eqr005j01skbwodfq08';

const mapContainer = ref<HTMLDivElement | null>(null);
const map = shallowRef<mapboxgl.Map | null>(null);
const marker = shallowRef<mapboxgl.Marker | null>(null);
const isDragging = ref(false);

const searchQuery = ref('');
const showSuggestions = ref(false);
const { results: suggestions } = useGeocodingSearch(searchQuery);

function hasCoords(lat: number | null, lng: number | null): boolean {
    return lat !== null && lng !== null;
}

async function reverseGeocode(lng: number, lat: number): Promise<void> {
    const token = mapboxgl.accessToken;

    if (!token) {
        return;
    }

    try {
        const url = `https://api.mapbox.com/geocoding/v5/mapbox.places/${lng},${lat}.json?access_token=${token}&country=ph&limit=1`;
        const response = await fetch(url);

        if (!response.ok) {
            return;
        }

        const data = await response.json();
        const placeName = data?.features?.[0]?.place_name as string | undefined;

        if (placeName) {
            emit('update:address', placeName);
        }
    } catch {
        // best-effort, silent fail
    }
}

function ensureMarker(lng: number, lat: number): void {
    if (!map.value) {
        return;
    }

    if (!marker.value) {
        marker.value = new mapboxgl.Marker({
            color: PIN_COLOR,
            draggable: true,
        })
            .setLngLat([lng, lat])
            .addTo(map.value);

        marker.value.on('dragstart', () => {
            isDragging.value = true;
        });

        marker.value.on('dragend', () => {
            isDragging.value = false;

            if (!marker.value) {
                return;
            }

            const pos = marker.value.getLngLat();

            emit('update:coordinates', pos.lat, pos.lng);
            reverseGeocode(pos.lng, pos.lat);
        });
    } else {
        marker.value.setLngLat([lng, lat]);
    }
}

function removeMarker(): void {
    marker.value?.remove();
    marker.value = null;
}

function selectSuggestion(result: {
    lat: number;
    lng: number;
    display_name: string;
}): void {
    if (!map.value) {
        return;
    }

    ensureMarker(result.lng, result.lat);
    map.value.flyTo({
        center: [result.lng, result.lat],
        zoom: 16,
        duration: 800,
    });
    emit('update:coordinates', result.lat, result.lng);
    emit('update:address', result.display_name);
    searchQuery.value = '';
    showSuggestions.value = false;
}

function selectFirstSuggestion(): void {
    if (suggestions.value.length > 0) {
        selectSuggestion(suggestions.value[0]);
    }
}

function onBlurSearch(): void {
    // Delay so clicks on suggestions register before we hide the list.
    window.setTimeout(() => {
        showSuggestions.value = false;
    }, 150);
}

watch(suggestions, (list) => {
    showSuggestions.value = list.length > 0;
});

onMounted(() => {
    if (!mapContainer.value) {
        return;
    }

    const center: [number, number] = hasCoords(
        props.latitude,
        props.longitude,
    )
        ? [props.longitude!, props.latitude!]
        : BUTUAN_CENTER;

    map.value = new mapboxgl.Map({
        container: mapContainer.value,
        style: MAP_STYLE,
        center,
        zoom: hasCoords(props.latitude, props.longitude) ? 16 : BUTUAN_ZOOM,
        attributionControl: false,
    });

    map.value.addControl(new mapboxgl.NavigationControl(), 'top-right');

    map.value.on('load', () => {
        if (hasCoords(props.latitude, props.longitude)) {
            ensureMarker(props.longitude!, props.latitude!);
        }
    });

    map.value.on('click', (event) => {
        const { lng, lat } = event.lngLat;

        ensureMarker(lng, lat);
        emit('update:coordinates', lat, lng);
        reverseGeocode(lng, lat);
    });
});

watch(
    () => [props.latitude, props.longitude] as const,
    ([lat, lng]) => {
        if (isDragging.value) {
            return;
        }

        if (!map.value) {
            return;
        }

        if (lat !== null && lng !== null) {
            ensureMarker(lng, lat);
        } else {
            removeMarker();
        }
    },
);

onUnmounted(() => {
    removeMarker();
    map.value?.remove();
    map.value = null;
});
</script>

<template>
    <div class="flex flex-col gap-2">
        <div class="relative">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-t-text-faint"
            />
            <Input
                v-model="searchQuery"
                placeholder="Search for an address in Butuan City…"
                class="pl-10"
                @keydown.enter.prevent="selectFirstSuggestion"
                @focus="showSuggestions = suggestions.length > 0"
                @blur="onBlurSearch"
            />
            <div
                v-if="showSuggestions && suggestions.length > 0"
                class="absolute top-full right-0 left-0 z-20 mt-1 overflow-hidden rounded-md border border-border bg-card shadow-[var(--shadow-3)]"
            >
                <button
                    v-for="(result, idx) in suggestions"
                    :key="`${result.lat}-${result.lng}-${idx}`"
                    type="button"
                    class="block w-full cursor-pointer px-3 py-2 text-left text-sm transition-colors hover:bg-accent"
                    @mousedown.prevent="selectSuggestion(result)"
                >
                    {{ result.display_name }}
                </button>
            </div>
        </div>

        <div
            ref="mapContainer"
            class="h-[320px] w-full overflow-hidden rounded-lg border border-t-border bg-t-surface-alt"
        />

        <p
            v-if="latitude !== null && longitude !== null"
            class="text-[10px] text-t-text-faint"
        >
            {{ latitude.toFixed(6) }}, {{ longitude.toFixed(6) }} &middot; Drag
            the pin to refine the exact location.
        </p>
        <p v-else class="text-[10px] text-t-text-faint">
            Search an address above or click on the map to drop a pin.
        </p>

        <p v-if="address" class="text-[10px] text-t-text-faint">
            {{ address }}
        </p>
    </div>
</template>
