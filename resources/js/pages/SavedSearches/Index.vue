<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../components/AppLayout.vue';

// Placeholder: the UI task replaces this page.
defineProps({
    savedSearches: { type: Object, required: true },
    savedSearchLimit: { type: Number, required: true },
});
</script>

<template>
    <AppLayout heading="Saved searches">
        <Head title="Saved searches" />
        <ul>
            <li v-for="search in savedSearches.data" :key="search.id">
                <Link :href="search.view_url">{{ search.description }}</Link>
            </li>
        </ul>
    </AppLayout>
</template>
