import mapboxgl from 'mapbox-gl';
import type { ExpressionSpecification, GeoJSONSource } from 'mapbox-gl';
import { onMounted, onUnmounted, ref, shallowRef } from 'vue';
import {
    CATEGORY_SVG_PATHS,
    getIncidentCategoryIcon,
} from '@/composables/useCategoryIcons';
import { useOsrmRoute } from '@/composables/useOsrmRoute';
import type { DispatchIncident, DispatchUnit } from '@/types/dispatch';

const BUTUAN_CENTER: [number, number] = [125.5406, 8.9475];
const BUTUAN_ZOOM = 13;

const DARK_STYLE = 'mapbox://styles/helderdene/cmmq06eqr005j01skbwodfq08';
const LIGHT_STYLE = 'mapbox://styles/helderdene/cmmq06eqr005j01skbwodfq08';

const PRIORITY_COLORS: ExpressionSpecification = [
    'match',
    ['get', 'priority'],
    'P1',
    '#E24B4A',
    'P2',
    '#EF9F27',
    'P3',
    '#1D9E75',
    'P4',
    '#378ADD',
    '#888888',
];

const STATUS_COLORS: ExpressionSpecification = [
    'match',
    ['get', 'status'],
    'AVAILABLE',
    '#1D9E75',
    'DISPATCHED',
    '#378ADD',
    'EN_ROUTE',
    '#378ADD',
    'ON_SCENE',
    '#EF9F27',
    'OFFLINE',
    '#6b7280',
    '#888888',
];

// --- Icon generation: white circle with colored icon inside ---

const ICON_SIZE = 64;

// Vehicle/truck path
const UNIT_ICON_PATH =
    'M18 9.5h-2V7H6.5c-.83 0-1.5.68-1.5 1.5v6.5h1.5c0 1.1.9 2 2 2s2-.9 2-2h3c0 1.1.9 2 2 2s2-.9 2-2H19v-3.5l-1-2zm-9.5 7.5c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm9-6.5 1.36 1.75H16V10.5h1.5zM15.5 17c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1z';

// Track which category icons are already loaded in the map
const loadedCategoryIcons = new Set<string>();

function buildCircleIconSvg(
    iconPath: string,
    color: string,
    useStroke: boolean = false,
): string {
    const pathAttrs = useStroke
        ? `stroke="${color}" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" fill="none"`
        : `fill="${color}"`;

    return [
        `<svg xmlns="http://www.w3.org/2000/svg" width="${ICON_SIZE}" height="${ICON_SIZE}" viewBox="0 0 24 24">`,
        `<circle cx="12" cy="12" r="11.5" fill="${color}"/>`,
        `<circle cx="12" cy="12" r="10" fill="white"/>`,
        `<path d="${iconPath}" ${pathAttrs}/>`,
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

const INCIDENT_COLORS: Record<string, string> = {
    P1: '#E24B4A',
    P2: '#EF9F27',
    P3: '#1D9E75',
    P4: '#378ADD',
};

const UNIT_COLORS: Record<string, string> = {
    AVAILABLE: '#1D9E75',
    DISPATCHED: '#378ADD',
    EN_ROUTE: '#378ADD',
    ON_SCENE: '#EF9F27',
    OFFLINE: '#6b7280',
};

type ClickCallback<T> = (id: T) => void;

type DispatchMapOptions = {
    center?: [number, number];
    zoom?: number;
};

export function useDispatchMap(
    containerId: string,
    options: DispatchMapOptions = {},
) {
    const map = shallowRef<mapboxgl.Map | null>(null);
    const isLoaded = ref(false);
    const initialCenter = options.center ?? BUTUAN_CENTER;
    const initialZoom = options.zoom ?? BUTUAN_ZOOM;

    const { getRoute } = useOsrmRoute();

    const unitPositions = new Map<string, [number, number]>();
    const activeAnimations = new Map<string, number>();

    let currentIncidentData: GeoJSON.FeatureCollection = {
        type: 'FeatureCollection',
        features: [],
    };
    let currentUnitData: GeoJSON.FeatureCollection = {
        type: 'FeatureCollection',
        features: [],
    };
    let currentConnectionData: GeoJSON.FeatureCollection = {
        type: 'FeatureCollection',
        features: [],
    };

    const incidentClickCallbacks: ClickCallback<string>[] = [];
    const unitClickCallbacks: ClickCallback<string>[] = [];
    const deselectCallbacks: (() => void)[] = [];

    async function loadIcons(): Promise<void> {
        if (!map.value) {
            return;
        }

        const promises: Promise<void>[] = [];

        // Load unit icons (status-based)
        for (const [key, color] of Object.entries(UNIT_COLORS)) {
            const name = `unit-${key}`;

            if (!map.value.hasImage(name)) {
                const m = map.value;

                promises.push(
                    loadSvgAsImage(
                        buildCircleIconSvg(UNIT_ICON_PATH, color),
                    ).then((img) => {
                        if (!m.hasImage(name)) {
                            m.addImage(name, img);
                        }
                    }),
                );
            }
        }

        // Load category icons for each priority color
        for (const [iconName, iconPath] of Object.entries(CATEGORY_SVG_PATHS)) {
            for (const [priority, color] of Object.entries(INCIDENT_COLORS)) {
                const name = `cat-${iconName}-${priority}`;

                if (
                    !loadedCategoryIcons.has(name) &&
                    !map.value.hasImage(name)
                ) {
                    const m = map.value;
                    // Lucide icons use strokes
                    const useStroke =
                        iconName !== 'AlertTriangle' &&
                        iconName !== 'Shield' &&
                        iconName !== 'Heart' &&
                        iconName !== 'Flame';

                    promises.push(
                        loadSvgAsImage(
                            buildCircleIconSvg(iconPath, color, useStroke),
                        ).then((img) => {
                            if (!m.hasImage(name)) {
                                m.addImage(name, img);
                            }

                            loadedCategoryIcons.add(name);
                        }),
                    );
                }
            }
        }

        await Promise.all(promises);
    }

    async function ensureCategoryIconsLoaded(
        incidents: DispatchIncident[],
    ): Promise<void> {
        if (!map.value) {
            return;
        }

        const promises: Promise<void>[] = [];

        for (const inc of incidents) {
            const iconName = getIncidentCategoryIcon(inc.incident_type);
            const iconPath =
                CATEGORY_SVG_PATHS[iconName] ??
                CATEGORY_SVG_PATHS.AlertTriangle;

            for (const [priority, color] of Object.entries(INCIDENT_COLORS)) {
                const name = `cat-${iconName}-${priority}`;

                if (
                    !loadedCategoryIcons.has(name) &&
                    !map.value.hasImage(name)
                ) {
                    const m = map.value;
                    const useStroke =
                        iconName !== 'AlertTriangle' &&
                        iconName !== 'Shield' &&
                        iconName !== 'Heart' &&
                        iconName !== 'Flame';

                    promises.push(
                        loadSvgAsImage(
                            buildCircleIconSvg(iconPath, color, useStroke),
                        ).then((img) => {
                            if (!m.hasImage(name)) {
                                m.addImage(name, img);
                            }

                            loadedCategoryIcons.add(name);
                        }),
                    );
                }
            }
        }

        if (promises.length > 0) {
            await Promise.all(promises);
        }
    }

    function addSources(): void {
        if (!map.value) {
            return;
        }

        map.value.addSource('incidents', {
            type: 'geojson',
            data: currentIncidentData,
            promoteId: 'id',
        });

        map.value.addSource('units', {
            type: 'geojson',
            data: currentUnitData,
            promoteId: 'id',
        });

        map.value.addSource('connections', {
            type: 'geojson',
            data: currentConnectionData,
        });
    }

    function addLayers(): void {
        if (!map.value) {
            return;
        }

        // --- Connection glow (wide soft line behind the dashed line) ---
        map.value.addLayer({
            id: 'connection-glow',
            type: 'line',
            source: 'connections',
            paint: {
                'line-color': PRIORITY_COLORS,
                'line-width': 8,
                'line-opacity': 0.25,
                'line-blur': 4,
            },
        });

        // --- Connection lines ---
        map.value.addLayer({
            id: 'connection-lines',
            type: 'line',
            source: 'connections',
            paint: {
                'line-color': PRIORITY_COLORS,
                'line-width': 3,
                'line-dasharray': [2, 3],
            },
        });

        // --- Incident glow (circle behind icon) ---
        map.value.addLayer({
            id: 'incident-halo',
            type: 'circle',
            source: 'incidents',
            paint: {
                'circle-radius': 20,
                'circle-color': PRIORITY_COLORS,
                'circle-opacity': 0.15,
                'circle-blur': 1,
            },
        });

        // --- Incident icon layer (category-specific icons) ---
        map.value.addLayer({
            id: 'incident-core',
            type: 'symbol',
            source: 'incidents',
            layout: {
                'icon-image': [
                    'concat',
                    'cat-',
                    ['get', 'category_icon'],
                    '-',
                    ['get', 'priority'],
                ] as unknown as ExpressionSpecification,
                'icon-size': 0.55,
                'icon-allow-overlap': true,
                'icon-ignore-placement': true,
                'icon-anchor': 'center',
            },
        });

        // --- Incident label ---
        map.value.addLayer({
            id: 'incident-label',
            type: 'symbol',
            source: 'incidents',
            layout: {
                'text-field': ['get', 'incident_no'],
                'text-font': ['DIN Pro Bold', 'Arial Unicode MS Bold'],
                'text-size': 9,
                'text-offset': [0, 1.8],
                'text-anchor': 'top',
                'text-allow-overlap': false,
            },
            paint: {
                'text-color': '#ffffff',
                'text-halo-color': '#000000',
                'text-halo-width': 1,
            },
        });

        // --- Unit glow ---
        map.value.addLayer({
            id: 'unit-glow',
            type: 'circle',
            source: 'units',
            paint: {
                'circle-radius': 18,
                'circle-color': STATUS_COLORS,
                'circle-opacity': 0.15,
                'circle-blur': 1,
            },
        });

        // --- Unit icon layer ---
        map.value.addLayer({
            id: 'unit-body',
            type: 'symbol',
            source: 'units',
            layout: {
                'icon-image': [
                    'concat',
                    'unit-',
                    ['get', 'status'],
                ] as unknown as ExpressionSpecification,
                'icon-size': 0.5,
                'icon-allow-overlap': true,
                'icon-ignore-placement': true,
                'icon-anchor': 'center',
            },
        });

        // --- Unit callsign label ---
        map.value.addLayer({
            id: 'unit-label',
            type: 'symbol',
            source: 'units',
            layout: {
                'text-field': ['get', 'callsign'],
                'text-font': ['DIN Pro Bold', 'Arial Unicode MS Bold'],
                'text-size': 9,
                'text-offset': [0, 1.6],
                'text-anchor': 'top',
                'text-allow-overlap': false,
            },
            paint: {
                'text-color': '#ffffff',
                'text-halo-color': '#000000',
                'text-halo-width': 1,
            },
        });
    }

    function addClickHandlers(): void {
        if (!map.value) {
            return;
        }

        map.value.on('click', 'incident-core', (e) => {
            if (e.features && e.features.length > 0) {
                const id = e.features[0].properties?.id as string;
                incidentClickCallbacks.forEach((cb) => cb(id));
            }
        });

        map.value.on('click', 'unit-body', (e) => {
            if (e.features && e.features.length > 0) {
                const id = e.features[0].properties?.id as string;
                unitClickCallbacks.forEach((cb) => cb(id));
            }
        });

        map.value.on('click', (e) => {
            if (!map.value) {
                return;
            }

            const incidentFeatures = map.value.queryRenderedFeatures(e.point, {
                layers: ['incident-core'],
            });
            const unitFeatures = map.value.queryRenderedFeatures(e.point, {
                layers: ['unit-body'],
            });

            if (incidentFeatures.length === 0 && unitFeatures.length === 0) {
                deselectCallbacks.forEach((cb) => cb());
            }
        });

        map.value.on('mouseenter', 'incident-core', () => {
            if (map.value) {
                map.value.getCanvas().style.cursor = 'pointer';
            }
        });

        map.value.on('mouseleave', 'incident-core', () => {
            if (map.value) {
                map.value.getCanvas().style.cursor = '';
            }
        });

        map.value.on('mouseenter', 'unit-body', () => {
            if (map.value) {
                map.value.getCanvas().style.cursor = 'pointer';
            }
        });

        map.value.on('mouseleave', 'unit-body', () => {
            if (map.value) {
                map.value.getCanvas().style.cursor = '';
            }
        });
    }

    onMounted(() => {
        map.value = new mapboxgl.Map({
            container: containerId,
            style: DARK_STYLE,
            center: initialCenter,
            zoom: initialZoom,
            pitch: 0,
            projection: 'mercator',
        });

        map.value.addControl(
            new mapboxgl.NavigationControl({ visualizePitch: true }),
            'top-right',
        );

        map.value.on('load', async () => {
            await loadIcons();
            isLoaded.value = true;
            addSources();
            addLayers();
            addClickHandlers();
        });
    });

    onUnmounted(() => {
        activeAnimations.forEach((frameId) => {
            cancelAnimationFrame(frameId);
        });
        activeAnimations.clear();
        map.value?.remove();
    });

    async function setIncidentData(
        incidents: DispatchIncident[],
    ): Promise<void> {
        // Ensure category icons are loaded before updating the data
        await ensureCategoryIconsLoaded(incidents);

        currentIncidentData = {
            type: 'FeatureCollection',
            features: incidents
                .filter((inc) => inc.coordinates !== null)
                .map((inc) => ({
                    type: 'Feature' as const,
                    id: inc.id,
                    geometry: {
                        type: 'Point' as const,
                        coordinates: [
                            inc.coordinates!.lng,
                            inc.coordinates!.lat,
                        ],
                    },
                    properties: {
                        id: inc.id,
                        priority: inc.priority,
                        status: inc.status,
                        incident_no: inc.incident_no,
                        category_icon: getIncidentCategoryIcon(
                            inc.incident_type,
                        ),
                    },
                })),
        };

        const source = map.value?.getSource('incidents') as
            | GeoJSONSource
            | undefined;
        source?.setData(currentIncidentData);
    }

    function setUnitData(units: DispatchUnit[]): void {
        units.forEach((unit) => {
            if (unit.coordinates) {
                unitPositions.set(unit.id, [
                    unit.coordinates.lng,
                    unit.coordinates.lat,
                ]);
            }
        });

        currentUnitData = {
            type: 'FeatureCollection',
            features: units
                .filter((unit) => unit.coordinates !== null)
                .map((unit) => ({
                    type: 'Feature' as const,
                    id: unit.id,
                    geometry: {
                        type: 'Point' as const,
                        coordinates: [
                            unit.coordinates!.lng,
                            unit.coordinates!.lat,
                        ],
                    },
                    properties: {
                        id: unit.id,
                        status: unit.status,
                        callsign: unit.callsign,
                    },
                })),
        };

        const source = map.value?.getSource('units') as
            | GeoJSONSource
            | undefined;
        source?.setData(currentUnitData);
    }

    function updateUnitPosition(
        unitId: string,
        lng: number,
        lat: number,
    ): void {
        unitPositions.set(unitId, [lng, lat]);

        const featureIndex = currentUnitData.features.findIndex(
            (f) => f.properties?.id === unitId,
        );

        if (featureIndex >= 0) {
            currentUnitData.features[featureIndex].geometry = {
                type: 'Point',
                coordinates: [lng, lat],
            };
        }

        const source = map.value?.getSource('units') as
            | GeoJSONSource
            | undefined;
        source?.setData(currentUnitData);
    }

    function animateUnitTo(
        unitId: string,
        toLng: number,
        toLat: number,
        durationMs: number = 1000,
    ): void {
        const existingFrame = activeAnimations.get(unitId);

        if (existingFrame) {
            cancelAnimationFrame(existingFrame);
        }

        const from = unitPositions.get(unitId);

        if (!from) {
            updateUnitPosition(unitId, toLng, toLat);

            return;
        }

        const [fromLng, fromLat] = from;
        const startTime = performance.now();

        function step(currentTime: number): void {
            const elapsed = currentTime - startTime;
            const t = Math.min(elapsed / durationMs, 1);
            const eased = 1 - Math.pow(1 - t, 3);

            const lng = fromLng + (toLng - fromLng) * eased;
            const lat = fromLat + (toLat - fromLat) * eased;

            updateUnitPosition(unitId, lng, lat);

            if (t < 1) {
                const frameId = requestAnimationFrame(step);
                activeAnimations.set(unitId, frameId);
            } else {
                activeAnimations.delete(unitId);
            }
        }

        const frameId = requestAnimationFrame(step);
        activeAnimations.set(unitId, frameId);
    }

    async function updateConnectionLines(
        assignments: Array<{
            incident: DispatchIncident;
            unit: DispatchUnit;
        }>,
    ): Promise<void> {
        const valid = assignments.filter(
            ({ incident, unit }) =>
                incident.coordinates !== null && unit.coordinates !== null,
        );

        // Show straight lines immediately while routes load
        currentConnectionData = {
            type: 'FeatureCollection',
            features: valid.map(({ incident, unit }) => ({
                type: 'Feature' as const,
                geometry: {
                    type: 'LineString' as const,
                    coordinates: [
                        [unit.coordinates!.lng, unit.coordinates!.lat],
                        [incident.coordinates!.lng, incident.coordinates!.lat],
                    ],
                },
                properties: {
                    priority: incident.priority,
                    incident_id: incident.id,
                    unit_id: unit.id,
                },
            })),
        };

        const source = map.value?.getSource('connections') as
            | GeoJSONSource
            | undefined;
        source?.setData(currentConnectionData);

        // Fetch road routes and replace lines as they resolve
        const routePromises = valid.map(async ({ incident, unit }) => {
            const from: [number, number] = [
                unit.coordinates!.lng,
                unit.coordinates!.lat,
            ];
            const to: [number, number] = [
                incident.coordinates!.lng,
                incident.coordinates!.lat,
            ];
            const route = await getRoute(from, to);

            return {
                type: 'Feature' as const,
                geometry: {
                    type: 'LineString' as const,
                    coordinates: route.coordinates,
                },
                properties: {
                    priority: incident.priority,
                    incident_id: incident.id,
                    unit_id: unit.id,
                },
            };
        });

        const features = await Promise.all(routePromises);

        currentConnectionData = {
            type: 'FeatureCollection',
            features,
        };

        source?.setData(currentConnectionData);
    }

    function flyToIncident(incident: DispatchIncident): void {
        if (!map.value || !incident.coordinates) {
            return;
        }

        map.value.flyTo({
            center: [incident.coordinates.lng, incident.coordinates.lat],
            zoom: 15,
            duration: 1000,
        });
    }

    function flyToUnit(unit: DispatchUnit): void {
        if (!map.value || !unit.coordinates) {
            return;
        }

        map.value.flyTo({
            center: [unit.coordinates.lng, unit.coordinates.lat],
            zoom: 15,
            duration: 1000,
        });
    }

    function switchStyle(dark: boolean): void {
        if (!map.value) {
            return;
        }

        map.value.setStyle(dark ? DARK_STYLE : LIGHT_STYLE);

        map.value.once('style.load', async () => {
            await loadIcons();
            addSources();
            addLayers();
            addClickHandlers();
        });
    }

    function onIncidentClick(cb: ClickCallback<string>): void {
        incidentClickCallbacks.push(cb);
    }

    function onUnitClick(cb: ClickCallback<string>): void {
        unitClickCallbacks.push(cb);
    }

    function onDeselect(cb: () => void): void {
        deselectCallbacks.push(cb);
    }

    return {
        map,
        isLoaded,
        setIncidentData,
        setUnitData,
        updateUnitPosition,
        animateUnitTo,
        updateConnectionLines,
        flyToIncident,
        flyToUnit,
        switchStyle,
        onIncidentClick,
        onUnitClick,
        onDeselect,
    };
}
