import maplibregl from 'maplibre-gl';
import type {
    ExpressionSpecification,
    GeoJSONSource,
    Map as MaplibreMap,
} from 'maplibre-gl';
import { onMounted, onUnmounted, ref, shallowRef } from 'vue';
import type { DispatchIncident, DispatchUnit } from '@/types/dispatch';

const BUTUAN_CENTER: [number, number] = [125.5406, 8.9475];
const BUTUAN_ZOOM = 13;

const DARK_STYLE =
    'https://basemaps.cartocdn.com/gl/dark-matter-gl-style/style.json';
const LIGHT_STYLE =
    'https://basemaps.cartocdn.com/gl/positron-gl-style/style.json';

const PRIORITY_COLORS: ExpressionSpecification = [
    'match',
    ['get', 'priority'],
    'P1',
    '#dc2626',
    'P2',
    '#ea580c',
    'P3',
    '#ca8a04',
    'P4',
    '#16a34a',
    '#888888',
];

const STATUS_COLORS: ExpressionSpecification = [
    'match',
    ['get', 'status'],
    'AVAILABLE',
    '#16a34a',
    'DISPATCHED',
    '#2563eb',
    'EN_ROUTE',
    '#2563eb',
    'ON_SCENE',
    '#ca8a04',
    'OFFLINE',
    '#6b7280',
    '#888888',
];

type ClickCallback<T> = (id: T) => void;

export function useDispatchMap(containerId: string) {
    const map = shallowRef<MaplibreMap | null>(null);
    const isLoaded = ref(false);

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

        // --- Connection lines ---
        map.value.addLayer({
            id: 'connection-lines',
            type: 'line',
            source: 'connections',
            paint: {
                'line-color': PRIORITY_COLORS,
                'line-width': 2,
                'line-dasharray': [2, 4],
            },
        });

        // --- Incident layers ---
        map.value.addLayer({
            id: 'incident-halo',
            type: 'circle',
            source: 'incidents',
            paint: {
                'circle-radius': 18,
                'circle-color': PRIORITY_COLORS,
                'circle-opacity': 0.15,
                'circle-blur': 1,
            },
        });

        map.value.addLayer({
            id: 'incident-pulse',
            type: 'circle',
            source: 'incidents',
            paint: {
                'circle-radius': 14,
                'circle-color': PRIORITY_COLORS,
                'circle-opacity': 0.08,
            },
        });

        map.value.addLayer({
            id: 'incident-border',
            type: 'circle',
            source: 'incidents',
            paint: {
                'circle-radius': 8,
                'circle-color': '#ffffff',
                'circle-opacity': 0.8,
            },
        });

        map.value.addLayer({
            id: 'incident-core',
            type: 'circle',
            source: 'incidents',
            paint: {
                'circle-radius': 6,
                'circle-color': PRIORITY_COLORS,
                'circle-stroke-width': 0,
                'circle-opacity': 1,
            },
        });

        // --- Unit layers ---
        map.value.addLayer({
            id: 'unit-glow',
            type: 'circle',
            source: 'units',
            paint: {
                'circle-radius': 14,
                'circle-color': STATUS_COLORS,
                'circle-opacity': 0.15,
                'circle-blur': 1,
            },
        });

        map.value.addLayer({
            id: 'unit-border',
            type: 'circle',
            source: 'units',
            paint: {
                'circle-radius': 7,
                'circle-color': '#ffffff',
                'circle-opacity': 0.9,
            },
        });

        map.value.addLayer({
            id: 'unit-body',
            type: 'circle',
            source: 'units',
            paint: {
                'circle-radius': 5,
                'circle-color': STATUS_COLORS,
                'circle-opacity': 1,
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
        map.value = new maplibregl.Map({
            container: containerId,
            style: DARK_STYLE,
            center: BUTUAN_CENTER,
            zoom: BUTUAN_ZOOM,
            maxPitch: 0,
            dragRotate: false,
        });

        map.value.on('load', () => {
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

    function setIncidentData(incidents: DispatchIncident[]): void {
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

    function updateConnectionLines(
        assignments: Array<{
            incident: DispatchIncident;
            unit: DispatchUnit;
        }>,
    ): void {
        currentConnectionData = {
            type: 'FeatureCollection',
            features: assignments
                .filter(
                    ({ incident, unit }) =>
                        incident.coordinates !== null &&
                        unit.coordinates !== null,
                )
                .map(({ incident, unit }) => ({
                    type: 'Feature' as const,
                    geometry: {
                        type: 'LineString' as const,
                        coordinates: [
                            [unit.coordinates!.lng, unit.coordinates!.lat],
                            [
                                incident.coordinates!.lng,
                                incident.coordinates!.lat,
                            ],
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

        map.value.once('style.load', () => {
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
