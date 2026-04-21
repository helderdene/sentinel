<script setup lang="ts">
// Phase 20 Plan 05 backend-first scaffold. Full implementation in Plan 07.
// Props span both create (categories only) and edit (personnel + signed URL +
// enrollment rows) paths; Plan 07 will define a strict form type.
defineProps<{
    personnel?: unknown;
    categories: unknown;
    photo_signed_url?: unknown;
    enrollment_rows?: unknown;
}>();
</script>

<template>
    <div>
        <h1>Personnel form (placeholder — Plan 07)</h1>
    </div>
</template>
