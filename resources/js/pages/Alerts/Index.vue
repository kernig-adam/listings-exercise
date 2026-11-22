<script setup>
import { onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../components/AppLayout.vue';
import ListingCard from '../../components/ListingCard.vue';
import Pagination from '../../components/Pagination.vue';
import { formatDate } from '../../format';

const props = defineProps({
    alertList: { type: Object, required: true },
});

// Viewing is a safe GET; marking read is this explicit PATCH, sent once. `only: ['alerts']` refreshes
// just the shared unread count, so the "New" pills stay for this view and are gone on the next one.
onMounted(() => {
    const shown = props.alertList.data;

    if (shown.some((alert) => alert.is_new)) {
        router.patch(
            '/alerts',
            { alert_ids: shown.map((alert) => alert.id), read: true },
            { preserveState: true, preserveScroll: true, only: ['alerts'] },
        );
    }
});
</script>

<template>
    <Head title="Alerts" />

    <AppLayout heading="Alerts">
        <ul v-if="alertList.data.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <li v-for="alert in alertList.data" :key="alert.id">
                <ListingCard :listing="alert.listing" :linked="alert.is_available">
                    <ul class="mt-3 flex flex-wrap gap-2">
                        <li v-if="alert.is_new"><span class="pill bg-brand-700 text-surface">New</span></li>
                        <li><span class="pill bg-brand-100 text-brand-900">{{ alert.reason_label }}</span></li>
                        <li v-if="!alert.is_available">
                            <span class="pill bg-behind-soft text-behind">No longer available</span>
                        </li>
                    </ul>

                    <p class="mt-3 text-sm text-muted">
                        <template v-if="alert.matched_deleted">Matched a search you've since deleted</template>
                        <template v-else>Matched: {{ alert.matched.join('; ') }}</template>
                    </p>

                    <p v-if="alert.alert_count > 1" class="mt-1 text-sm text-muted">
                        First alerted <time :datetime="alert.first_alerted_at">{{ formatDate(alert.first_alerted_at) }}</time>
                    </p>
                </ListingCard>
            </li>
        </ul>

        <div v-else class="rounded-card border border-dashed border-line bg-surface p-8 text-center">
            <h2 class="text-xl font-bold text-ink">No alerts yet</h2>
            <p class="mx-auto mt-2 max-w-prose text-muted">
                When a new home matches one of your saved searches, it appears here, so you only hear about homes you asked for.
            </p>
            <Link href="/" class="btn-primary mt-5">Save a search to get alerts</Link>
        </div>

        <Pagination :meta="alertList.meta" :links="alertList.links" />
    </AppLayout>
</template>
