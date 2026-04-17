<script setup lang="ts">
import type { IncidentType } from '@/types';
import { PRIORITY_BG, PRIORITY_COLORS } from '@/types';
import { computed } from 'vue';
import PriorityBadge from './PriorityBadge.vue';

const props = defineProps<{
    type: IncidentType;
    selected: boolean;
}>();

const emit = defineEmits<{
    select: [];
}>();

const color = computed(
    () => PRIORITY_COLORS[props.type.default_priority] ?? '#64748b'
);
const bg = computed(
    () => PRIORITY_BG[props.type.default_priority] ?? 'rgba(100,116,139,.08)'
);

interface IconDef {
    paths: string[];
    stroke: boolean;
}

/**
 * Per-type icons keyed by incident type code.
 * All stroke-based, 24×24 viewBox.
 */
const CODE_ICONS: Record<string, IconDef> = {
    // ── Medical ──────────────────────────────────────────
    'MED-001': {
        // Cardiac Arrest → Heart
        paths: [
            'M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z',
        ],
        stroke: true,
    },
    'MED-002': {
        // Stroke → Heart-pulse / activity
        paths: ['M22 12h-4l-3 9L9 3l-3 9H2'],
        stroke: true,
    },
    'MED-003': {
        // Severe Bleeding → Droplet
        paths: [
            'M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5S5 13 5 15a7 7 0 0 0 7 7z',
        ],
        stroke: true,
    },
    'MED-004': {
        // Difficulty Breathing → Wind
        paths: [
            'M17.7 7.7A2.5 2.5 0 0 1 21 10c0 1.38-1.12 2.5-2.5 2.5H3',
            'M9.6 4.6A2 2 0 0 1 13 6c0 1.1-.9 2-2 2H3',
            'M12.6 19.4A2 2 0 0 0 16 18c0-1.1-.9-2-2-2H3',
        ],
        stroke: true,
    },
    'MED-005': {
        // Seizure → Zap
        paths: ['M13 2L3 14h9l-1 8 10-12h-9l1-8z'],
        stroke: true,
    },
    'MED-006': {
        // Allergic Reaction → Shield with alert
        paths: [
            'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z',
            'M12 8v4',
            'M12 16h.01',
        ],
        stroke: true,
    },
    'MED-007': {
        // Abdominal Pain → User with alert
        paths: [
            'M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2',
            'M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z',
            'M20 8v4',
            'M20 16h0',
        ],
        stroke: true,
    },
    'MED-008': {
        // Minor Injury → Band-aid / adhesive bandage
        paths: [
            'M18 2l-4 4',
            'M10 6L6 10',
            'M7.5 7.5l-2.83 2.83a2 2 0 0 0 0 2.83l6.17 6.17a2 2 0 0 0 2.83 0l2.83-2.83a2 2 0 0 0 0-2.83L10.33 7.5a2 2 0 0 0-2.83 0z',
        ],
        stroke: true,
    },
    'MED-009': {
        // Fever / Illness → Thermometer
        paths: [
            'M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z',
        ],
        stroke: true,
    },
    'MED-010': {
        // Animal Bite → Crosshair / target
        paths: [
            'M12 22c5.52 0 10-4.48 10-10S17.52 2 12 2 2 6.48 2 12s4.48 10 10 10z',
            'M22 12h-4',
            'M6 12H2',
            'M12 6V2',
            'M12 22v-4',
        ],
        stroke: true,
    },

    // ── Fire ─────────────────────────────────────────────
    'FIR-001': {
        // Structure Fire → Home
        paths: [
            'M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z',
            'M9 22V12h6v10',
        ],
        stroke: true,
    },
    'FIR-002': {
        // Vehicle Fire → Car
        paths: [
            'M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9L18 10l-2.7-3.4c-.4-.5-1-.8-1.6-.8H10c-.6 0-1.2.3-1.6.8L5.7 10l-2.5 1.1C2.4 11.3 2 12.1 2 13v3c0 .6.4 1 1 1h2',
            'M7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4z',
            'M17 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4z',
        ],
        stroke: true,
    },
    'FIR-003': {
        // Industrial Fire → Factory
        paths: [
            'M2 20a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8l-7 5V8l-7 5V4a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2z',
        ],
        stroke: true,
    },
    'FIR-004': {
        // Brush / Grass Fire → Tree
        paths: [
            'M12 22v-7',
            'M17 8l-5-6-5 6h10z',
            'M18 13l-6-5-6 5h12z',
        ],
        stroke: true,
    },
    'FIR-005': {
        // Electrical Fire → Power / Plug
        paths: [
            'M12 2v10',
            'M18.4 6.6a9 9 0 1 1-12.77.04',
        ],
        stroke: true,
    },
    'FIR-006': {
        // Small Contained Fire → Flame (small)
        paths: [
            'M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.07-2.14 0-5.5 2-6.5 0 3.5 2.56 5.5 4 6.5s2.5 3.5 2.5 5c0 3.31-2.69 4.5-6 4.5S5 17.31 5 14c0-1.38.5-2.5 1-3.5.36.53.72 1 1.16 1.36',
        ],
        stroke: true,
    },

    // ── Natural Disaster ─────────────────────────────────
    'NAT-001': {
        // Earthquake → Seismograph line
        paths: ['M2 12h2l3-9 4 18 4-18 3 9h4'],
        stroke: true,
    },
    'NAT-002': {
        // Flood → Waves
        paths: [
            'M2 6c.6.5 1.2 1 2.5 1C7 7 7 5 9.5 5c2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1',
            'M2 12c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2s2.4 2 5 2c2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1',
            'M2 18c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2s2.4 2 5 2c2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1',
        ],
        stroke: true,
    },
    'NAT-003': {
        // Landslide → Mountain
        paths: ['M8 3l4 8 5-5 5 15H2L8 3z'],
        stroke: true,
    },
    'NAT-004': {
        // Typhoon → Tornado lines
        paths: [
            'M21 4H3',
            'M18 8H6',
            'M19 12H9',
            'M16 16h-6',
            'M11 20H9',
        ],
        stroke: true,
    },
    'NAT-005': {
        // Storm Surge → Wave with arrow
        paths: [
            'M2 15c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2s2.4 2 5 2c2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1',
            'M12 2v8',
            'M8 6l4-4 4 4',
        ],
        stroke: true,
    },
    'NAT-006': {
        // Tornado → Cloud + lightning
        paths: [
            'M6 16.3a4 4 0 0 1-.67-7.94A5.5 5.5 0 0 1 16.9 10h.59a4 4 0 0 1 .67 7.94',
            'M13 11l-4 6h6l-4 6',
        ],
        stroke: true,
    },

    // ── Vehicular ────────────────────────────────────────
    'VEH-001': {
        // Multi-Vehicle Collision → Two overlapping rectangles
        paths: [
            'M10 17H2V9h12v8h-4z',
            'M22 17h-8V9h12v8h-4z',
            'M6 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4z',
            'M18 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4z',
        ],
        stroke: true,
    },
    'VEH-002': {
        // Vehicle vs Pedestrian → Person walking
        paths: [
            'M14 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4z',
            'M10 21l-2-5-3-3 4-3 4 3v8',
            'M18 14l-4-3',
        ],
        stroke: true,
    },
    'VEH-003': {
        // Single Vehicle → Car
        paths: [
            'M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9L18 10l-2.7-3.4c-.4-.5-1-.8-1.6-.8H10c-.6 0-1.2.3-1.6.8L5.7 10l-2.5 1.1C2.4 11.3 2 12.1 2 13v3c0 .6.4 1 1 1h2',
            'M7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4z',
            'M17 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4z',
        ],
        stroke: true,
    },
    'VEH-004': {
        // Motorcycle → Bike
        paths: [
            'M5 19a4 4 0 1 0 0-8 4 4 0 0 0 0 8z',
            'M19 19a4 4 0 1 0 0-8 4 4 0 0 0 0 8z',
            'M5 15l7-7 4 2 3-3',
        ],
        stroke: true,
    },
    'VEH-005': {
        // Minor Fender Bender → Car with dent
        paths: [
            'M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9L18 10l-2.7-3.4c-.4-.5-1-.8-1.6-.8H10c-.6 0-1.2.3-1.6.8L5.7 10l-2.5 1.1C2.4 11.3 2 12.1 2 13v3c0 .6.4 1 1 1h2',
            'M7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4z',
            'M17 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4z',
        ],
        stroke: true,
    },
    'VEH-006': {
        // Vehicle Breakdown → Wrench
        paths: [
            'M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z',
        ],
        stroke: true,
    },

    // ── Crime / Security ─────────────────────────────────
    'CRM-001': {
        // Active Shooter → AlertOctagon
        paths: [
            'M7.86 2h8.28L22 7.86v8.28L16.14 22H7.86L2 16.14V7.86L7.86 2z',
            'M12 8v4',
            'M12 16h.01',
        ],
        stroke: true,
    },
    'CRM-002': {
        // Bomb Threat → Alert triangle
        paths: [
            'M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z',
            'M12 9v4',
            'M12 17h.01',
        ],
        stroke: true,
    },
    'CRM-003': {
        // Assault → Shield with X
        paths: [
            'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z',
            'M9.5 9l5 5',
            'M14.5 9l-5 5',
        ],
        stroke: true,
    },
    'CRM-004': {
        // Robbery → Siren
        paths: [
            'M7 18v-6a5 5 0 1 1 10 0v6',
            'M5 21h14',
            'M12 3v2',
            'M4.22 7.22l1.42 1.42',
            'M19.78 7.22l-1.42 1.42',
        ],
        stroke: true,
    },
    'CRM-005': {
        // Domestic Violence → Home with alert
        paths: [
            'M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z',
            'M12 9v4',
            'M12 17h.01',
        ],
        stroke: true,
    },
    'CRM-006': {
        // Suspicious Activity → Eye
        paths: [
            'M1 12s4-8 11-8c7 0 11 8 11 8s-4 8-11 8c-7 0-11-8-11-8z',
            'M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6z',
        ],
        stroke: true,
    },
    'CRM-007': {
        // Theft → Lock
        paths: [
            'M19 11H5a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7a2 2 0 0 0-2-2z',
            'M7 11V7a5 5 0 0 1 10 0v4',
        ],
        stroke: true,
    },

    // ── Hazmat ───────────────────────────────────────────
    'HAZ-001': {
        // Chemical Spill → Flask
        paths: [
            'M9 3h6',
            'M10 3v6.5L4 18a2 2 0 0 0 1.7 3h12.6a2 2 0 0 0 1.7-3L14 9.5V3',
        ],
        stroke: true,
    },
    'HAZ-002': {
        // Gas Leak → Cloud
        paths: [
            'M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9z',
        ],
        stroke: true,
    },
    'HAZ-003': {
        // Radioactive Material → Radiation / atom
        paths: [
            'M12 12m-2 0a2 2 0 1 0 4 0 2 2 0 1 0-4 0',
            'M12 2v4',
            'M4.93 7.5l3.46 2',
            'M4.93 16.5l3.46-2',
            'M12 18v4',
            'M19.07 16.5l-3.46-2',
            'M19.07 7.5l-3.46 2',
        ],
        stroke: true,
    },
    'HAZ-004': {
        // Fuel Spill → Droplet
        paths: [
            'M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5S5 13 5 15a7 7 0 0 0 7 7z',
        ],
        stroke: true,
    },
    'HAZ-005': {
        // Minor Hazmat Release → Wind
        paths: [
            'M17.7 7.7A2.5 2.5 0 0 1 21 10c0 1.38-1.12 2.5-2.5 2.5H3',
            'M9.6 4.6A2 2 0 0 1 13 6c0 1.1-.9 2-2 2H3',
            'M12.6 19.4A2 2 0 0 0 16 18c0-1.1-.9-2-2-2H3',
        ],
        stroke: true,
    },

    // ── Water Rescue ─────────────────────────────────────
    'WTR-001': {
        // Drowning → Life buoy
        paths: [
            'M12 12m-4 0a4 4 0 1 0 8 0 4 4 0 1 0-8 0',
            'M12 12m-10 0a10 10 0 1 0 20 0 10 10 0 1 0-20 0',
            'M15 9l3.5-3.5',
            'M9 15l-3.5 3.5',
            'M15 15l3.5 3.5',
            'M9 9L5.5 5.5',
        ],
        stroke: true,
    },
    'WTR-002': {
        // Boat Capsized → Anchor
        paths: [
            'M12 22V8',
            'M5 12H2a10 10 0 0 0 20 0h-3',
            'M12 8a4 4 0 1 0 0-8 4 4 0 0 0 0 8z',
        ],
        stroke: true,
    },
    'WTR-003': {
        // Flood Rescue → Waves
        paths: [
            'M2 6c.6.5 1.2 1 2.5 1C7 7 7 5 9.5 5c2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1',
            'M2 12c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2s2.4 2 5 2c2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1',
            'M2 18c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2s2.4 2 5 2c2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1',
        ],
        stroke: true,
    },
    'WTR-004': {
        // Swift Water Rescue → Arrow up + wave
        paths: [
            'M2 15c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2s2.4 2 5 2c2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1',
            'M12 2v8',
            'M8 6l4-4 4 4',
        ],
        stroke: true,
    },
    'WTR-005': {
        // Person in Water → Person + wave
        paths: [
            'M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2',
            'M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z',
            'M2 18c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2s2.4 2 5 2c2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1',
        ],
        stroke: true,
    },

    // ── Public Disturbance ───────────────────────────────
    'PUB-001': {
        // Riot / Civil Unrest → Users
        paths: [
            'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2',
            'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z',
            'M23 21v-2a4 4 0 0 0-3-3.87',
            'M16 3.13a4 4 0 0 1 0 7.75',
        ],
        stroke: true,
    },
    'PUB-002': {
        // Large Gathering → Users group (three people)
        paths: [
            'M18 21v-2a4 4 0 0 0-4-4H10a4 4 0 0 0-4 4v2',
            'M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z',
        ],
        stroke: true,
    },
    'PUB-003': {
        // Noise Complaint → Volume / Speaker
        paths: [
            'M11 5L6 9H2v6h4l5 4V5z',
            'M15.54 8.46a5 5 0 0 1 0 7.07',
            'M19.07 4.93a10 10 0 0 1 0 14.14',
        ],
        stroke: true,
    },
    'PUB-004': {
        // Illegal Dumping → Trash
        paths: [
            'M3 6h18',
            'M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2',
            'M10 11v6',
            'M14 11v6',
        ],
        stroke: true,
    },

    // ── Other ────────────────────────────────────────────
    OTHER_EMERGENCY: {
        // Other → Question circle
        paths: [
            'M12 22c5.52 0 10-4.48 10-10S17.52 2 12 2 2 6.48 2 12s4.48 10 10 10z',
            'M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3',
            'M12 17h.01',
        ],
        stroke: true,
    },
};

/**
 * Fallback category icons (used when a code is not in CODE_ICONS).
 */
const CATEGORY_ICONS: Record<string, IconDef> = {
    Medical: {
        paths: ['M10 3h4v7h7v4h-7v7h-4v-7H3v-4h7V3Z'],
        stroke: false,
    },
    Fire: {
        paths: [
            'M12 2c.5 3-1.5 5-1.5 5s3-1 4 2c1 3-1 5.5-1 5.5s2-.5 2.5-2.5c2 3 0 7-3.5 8.5C9 22 6 19 6 15.5c0-3 2-5.5 3-7C10 7 11.5 4 12 2Z',
        ],
        stroke: false,
    },
    'Natural Disaster': {
        paths: [
            'M13 2L4.1 12.9a.5.5 0 0 0 .4.8H11l-1 8.3 8.9-10.9a.5.5 0 0 0-.4-.8H13l1-8.3Z',
        ],
        stroke: false,
    },
    Vehicular: {
        paths: [
            'M5 17h1a2 2 0 1 0 4 0h4a2 2 0 1 0 4 0h1a1 1 0 0 0 1-1v-4a1 1 0 0 0-.3-.7L17.4 9H16l-3-4H8L5 9h-.6L3.1 11.3A1 1 0 0 0 3 12v4a1 1 0 0 0 1 1h1Z',
        ],
        stroke: true,
    },
    'Crime / Security': {
        paths: [
            'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z',
        ],
        stroke: true,
    },
    Hazmat: {
        paths: [
            'M12 22c5.52 0 10-4.48 10-10S17.52 2 12 2 2 6.48 2 12s4.48 10 10 10z',
            'M12 8v4',
            'M12 16h.01',
        ],
        stroke: true,
    },
    'Water Rescue': {
        paths: [
            'M2 16c1.5-1.5 3-2 4.5-2s3 .5 4.5 2c1.5 1.5 3 2 4.5 2s3-.5 4.5-2',
            'M2 10c1.5-1.5 3-2 4.5-2s3 .5 4.5 2c1.5 1.5 3 2 4.5 2s3-.5 4.5-2',
        ],
        stroke: true,
    },
    'Public Disturbance': {
        paths: [
            'M18 8a6 6 0 0 1 0 8',
            'M13 3L7 8H4a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h3l6 5V3z',
            'M15 11a3 3 0 0 1 0 4',
        ],
        stroke: true,
    },
};

const DEFAULT_ICON: IconDef = {
    paths: [
        'M12 22c5.52 0 10-4.48 10-10S17.52 2 12 2 2 6.48 2 12s4.48 10 10 10z',
        'M12 8v4',
        'M12 16h.01',
    ],
    stroke: true,
};

const icon = computed<IconDef>(() => {
    return (
        CODE_ICONS[props.type.code] ??
        CATEGORY_ICONS[props.type.category] ??
        DEFAULT_ICON
    );
});
</script>

<template>
    <button
        class="flex cursor-pointer flex-col gap-2 rounded-xl border bg-t-surface p-3 text-left transition-all duration-200"
        :class="
            selected
                ? 'scale-[0.97] shadow-lg'
                : 'hover:shadow-md'
        "
        :style="{
            borderColor: selected ? color : 'var(--t-border)',
            boxShadow: selected ? `0 0 0 1px ${color}, 0 4px 12px ${color}20` : undefined,
        }"
        @click="emit('select')"
    >
        <!-- Icon area -->
        <div
            class="flex h-11 w-11 items-center justify-center rounded-lg"
            :style="{ backgroundColor: bg }"
        >
            <svg
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="none"
                :style="{ color: color }"
            >
                <path
                    v-for="(d, i) in icon.paths"
                    :key="i"
                    :d="d"
                    :stroke="icon.stroke ? 'currentColor' : undefined"
                    :stroke-width="icon.stroke ? '2' : undefined"
                    :stroke-linecap="icon.stroke ? 'round' : undefined"
                    :stroke-linejoin="icon.stroke ? 'round' : undefined"
                    :fill="icon.stroke ? 'none' : 'currentColor'"
                />
            </svg>
        </div>

        <!-- Type name -->
        <span class="text-[13px] font-semibold leading-tight text-t-text">
            {{ type.name }}
        </span>

        <!-- Description (truncated) -->
        <span
            v-if="type.description"
            class="line-clamp-2 text-[11px] leading-snug text-t-text-dim"
        >
            {{ type.description }}
        </span>

        <!-- Priority badge -->
        <PriorityBadge :priority="type.default_priority" small />
    </button>
</template>
