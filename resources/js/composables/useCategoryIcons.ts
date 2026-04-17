import {
    AlertTriangle,
    Anchor,
    Biohazard,
    Car,
    CloudLightning,
    Flame,
    Heart,
    Megaphone,
    Shield,
    Siren,
    Waves,
} from 'lucide-vue-next';
import type { Component } from 'vue';

/**
 * SVG paths (24x24 viewBox) for map marker icons.
 * These are simplified Lucide icon paths optimized for small rendering.
 */
export const CATEGORY_SVG_PATHS: Record<string, string> = {
    Heart: 'M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z',
    Flame: 'M12 22c-4.97 0-8-3.58-8-7.5 0-3.5 2.5-6.5 4-8 .36-.36.94-.1.94.42 0 1.5.5 3.08 2.06 3.08 1.5 0 2-1 2-2.5 0-2-1-3.5-1-5.5 0-.55.45-.82.9-.55C16.5 4 20 8 20 14.5c0 3.92-3.03 7.5-8 7.5z',
    CloudLightning:
        'M13 10V4a1 1 0 0 0-1.45-.89L6.89 5.56A2 2 0 0 0 6 7.5V9a4 4 0 0 0-1.17 7.83A2 2 0 0 0 6.5 18h4.09l-.59.95a1 1 0 0 0 .86 1.55h.14L13 16l-2-1 2-5zM17.5 18a4 4 0 0 0 .67-7.94A5.5 5.5 0 0 0 7.1 10H7a4 4 0 0 0 0 8h10.5z',
    Car: 'M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9L18 10l-2.7-3.4c-.4-.5-1-.8-1.6-.8H10c-.6 0-1.2.3-1.6.8L5.7 10l-2.5 1.1C2.4 11.3 2 12.1 2 13v3c0 .6.4 1 1 1h2m14 0a2 2 0 1 1-4 0m4 0a2 2 0 0 0-4 0m-8 0a2 2 0 1 1-4 0m4 0a2 2 0 0 0-4 0',
    Shield: 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z',
    Biohazard:
        'M12 12m-2 0a2 2 0 1 0 4 0a2 2 0 1 0-4 0M12 7.5c-1.38 0-2.63.56-3.54 1.46M12 7.5c1.38 0 2.63.56 3.54 1.46M7.05 16.5c.68 1.19 1.77 2.1 3.08 2.5M16.95 16.5c-.68 1.19-1.77 2.1-3.08 2.5M5 12c0-1.93.78-3.68 2.05-4.95M19 12c0-1.93-.78-3.68-2.05-4.95M7.05 16.5C5.78 15.23 5 13.48 5 11.55M16.95 16.5C18.22 15.23 19 13.48 19 11.55',
    Waves: 'M2 6c.6.5 1.2 1 2.5 1C7 7 7 5 9.5 5c2.6 0 2.4 2 5 2 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1M2 12c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2s2.4 2 5 2c2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1M2 18c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2s2.4 2 5 2c2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1',
    Megaphone: 'M3 11l18-5v12L3 13v-2zm0 0v2m15 0v-6M6 15l-1 5h2l1-5',
    AlertTriangle:
        'M12 5.5c-.38 0-.73.2-.92.53l-4.86 8.4c-.19.33-.19.74 0 1.07.19.34.54.54.92.54h9.72c.38 0 .73-.2.92-.54.19-.33.19-.74 0-1.07l-4.86-8.4A1.06 1.06 0 0 0 12 5.5zm.5 8.5a.75.75 0 1 1-1 0 .75.75 0 0 1 1 0zM12 12a.5.5 0 0 1-.5-.5v-2a.5.5 0 1 1 1 0v2a.5.5 0 0 1-.5.5z',
    Siren: 'M7 18v-6a5 5 0 1 1 10 0v6M5 21h14M12 3v2M4.22 7.22l1.42 1.42M19.78 7.22l-1.42 1.42M2 12h2M20 12h2',
    Anchor: 'M12 22V8M12 8a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM5 12H2a10 10 0 0 0 20 0h-3',
};

/**
 * Map of icon names to Lucide Vue components for use in templates.
 */
export const CATEGORY_COMPONENTS: Record<string, Component> = {
    Heart,
    Flame,
    CloudLightning,
    Car,
    Shield,
    Biohazard,
    Waves,
    Megaphone,
    AlertTriangle,
    Siren,
    Anchor,
};

/**
 * Get the SVG path for a category icon name.
 * Falls back to AlertTriangle.
 */
export function getCategorySvgPath(
    iconName: string | null | undefined,
): string {
    return (
        CATEGORY_SVG_PATHS[iconName ?? ''] ?? CATEGORY_SVG_PATHS.AlertTriangle
    );
}

/**
 * Get the Vue component for a category icon name.
 * Falls back to AlertTriangle.
 */
export function getCategoryComponent(
    iconName: string | null | undefined,
): Component {
    return (
        CATEGORY_COMPONENTS[iconName ?? ''] ?? CATEGORY_COMPONENTS.AlertTriangle
    );
}

/**
 * Extract the category icon name from a DispatchIncident's incident_type.
 */
export function getIncidentCategoryIcon(
    incidentType: {
        incident_category?: { icon: string } | null;
    } | null,
): string {
    return incidentType?.incident_category?.icon ?? 'AlertTriangle';
}
