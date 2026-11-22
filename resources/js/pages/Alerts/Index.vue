<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../components/AppLayout.vue';

// Placeholder: the UI task replaces this page.
defineProps({
    alertList: { type: Object, required: true },
});
</script>

<template>
    <AppLayout heading="Alerts">
        <Head title="Alerts" />
        <ul>
            <li v-for="alert in alertList.data" :key="alert.id">
                <Link :href="`/listings/${alert.listing.id}`">
                    {{ alert.reason_label }}: {{ alert.listing.address_line_1 }}
                </Link>
            </li>
        </ul>
    </AppLayout>
</template>
