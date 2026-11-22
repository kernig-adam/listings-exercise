<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../components/AppLayout.vue';
import { formatDate, formatPrice } from '../../format';

defineProps({
    listing: { type: Object, required: true },
});

// The listings page remembers its URL, so back returns to the same filters and page.
const backUrl = sessionStorage.getItem('listingsUrl') ?? '/';
</script>

<template>
    <Head :title="listing.address_line_1" />

    <AppLayout :heading="listing.address_line_1" :subheading="`${listing.city}, ${listing.postcode}`">
        <div class="overflow-hidden rounded-card border border-line bg-surface shadow-card">
            <img
                :src="listing.image_url"
                alt=""
                decoding="async"
                class="aspect-card w-full object-cover"
            />

            <div class="p-8">
            <p class="text-3xl font-bold">{{ formatPrice(listing.price) }}</p>

            <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-medium text-muted">Bedrooms</dt>
                    <dd>{{ listing.bedrooms }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-muted">Bathrooms</dt>
                    <dd>{{ listing.bathrooms }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-muted">Property type</dt>
                    <dd>{{ listing.property_type_label }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-muted">Listed</dt>
                    <dd>{{ formatDate(listing.listed_at) ?? '—' }}</dd>
                </div>
            </dl>

            <p class="mt-6 border-t border-line pt-6 text-sm text-muted">
                Marketed by {{ listing.branch.name }} ({{ listing.branch.region }})
            </p>
            <p class="mt-1 text-xs text-muted">Ref: {{ listing.reference }}</p>
            </div>
        </div>

        <Link :href="backUrl" class="mt-6 inline-block text-sm text-brand-700 underline hover:text-brand-900 focus-ring">
            &larr; Back to all listings
        </Link>
    </AppLayout>
</template>
