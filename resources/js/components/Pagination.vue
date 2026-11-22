<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    meta: { type: Object, required: true },
    links: { type: Object, required: true },
});
</script>

<template>
    <nav v-if="meta.last_page > 1" aria-label="Pagination" class="mt-8 flex items-center justify-center gap-4">
        <Link v-if="links.prev" :href="links.prev" preserve-scroll class="btn-secondary">Previous</Link>
        <span v-else class="inline-flex min-h-11 items-center rounded-control border border-line px-5 text-sm text-muted">
            Previous
        </span>

        <span class="text-sm text-muted">Page {{ meta.current_page }} of {{ meta.last_page }}</span>

        <Link v-if="links.next" :href="links.next" preserve-scroll class="btn-secondary">Next</Link>
        <span v-else class="inline-flex min-h-11 items-center rounded-control border border-line px-5 text-sm text-muted">
            Next
        </span>
    </nav>
</template>
