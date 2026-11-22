<script setup>
import { computed, ref, watchEffect } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '../../components/AppLayout.vue';
import ListingCard from '../../components/ListingCard.vue';
import ListingFilters from '../../components/ListingFilters.vue';
import Pagination from '../../components/Pagination.vue';
import SaveSearch from '../../components/SaveSearch.vue';
import { toQuery } from '../../query';

const props = defineProps({
    listings: { type: Object, required: true },
    propertyTypes: { type: Array, required: true },
    regionOptions: { type: Array, required: true },
    priceOptions: { type: Array, required: true },
    bedroomOptions: { type: Array, required: true },
    bathroomOptions: { type: Array, required: true },
    filters: { type: Object, required: true },
    currentSearchSaved: { type: Boolean, default: false },
});

const page = usePage();
const processing = ref(false);

// Any filter applied (from the URL), using the same serialiser as Search and Save.
const hasFilters = computed(() => Object.keys(toQuery(props.filters)).length > 0);

// Remembered so a listing's "Back to all listings" returns to these filters and this page.
watchEffect(() => sessionStorage.setItem('listingsUrl', page.url));

function search(query) {
    router.get('/', query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        // Inertia cancels the in-flight visit when a new one starts, so rapid
        // searches can't land out of order.
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
    });
}
</script>

<template>
    <Head title="Properties for sale" />

    <AppLayout heading="Properties for sale" subheading="Across our branches.">
        <a
            href="#results"
            class="sr-only focus:not-sr-only focus:mb-4 focus:inline-block focus:bg-surface focus:px-4 focus:py-2 focus:text-ink focus-ring"
        >
            Skip to results
        </a>

        <ListingFilters
            :filters="filters"
            :property-types="propertyTypes"
            :region-options="regionOptions"
            :price-options="priceOptions"
            :bedroom-options="bedroomOptions"
            :bathroom-options="bathroomOptions"
            :errors="page.props.errors"
            :processing="processing"
            @search="search"
        />

        <section id="results" tabindex="-1" class="mt-8 focus:outline-none" aria-labelledby="results-heading">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <h2 id="results-heading" class="text-xl font-bold text-ink" aria-live="polite">
                    <template v-if="processing">Loading…</template>
                    <template v-else>{{ listings.meta.total }} {{ listings.meta.total === 1 ? 'home' : 'homes' }} found</template>
                </h2>

                <SaveSearch
                    v-if="listings.data.length"
                    :filters="filters"
                    :saved="currentSearchSaved"
                />
            </div>

            <ul
                v-if="listings.data.length"
                class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
                :class="processing && 'opacity-50 transition'"
            >
                <li v-for="listing in listings.data" :key="listing.id">
                    <ListingCard :listing="listing" heading-tag="h3" />
                </li>
            </ul>

            <div v-else class="mt-6 rounded-card border border-dashed border-line bg-surface p-8 text-center">
                <template v-if="hasFilters">
                    <p class="text-ink">
                        No homes match yet. Save this search and we'll add new matching homes to your Alerts.
                    </p>
                    <div class="mt-4 flex justify-center">
                        <SaveSearch
                            :filters="filters"
                            :saved="currentSearchSaved"
                        />
                    </div>
                </template>
                <p v-else class="text-ink">No homes are listed right now. Please check back soon.</p>
            </div>

            <Pagination :meta="listings.meta" :links="listings.links" />
        </section>
    </AppLayout>
</template>
