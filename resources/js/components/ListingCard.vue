<script setup>
import { Link } from '@inertiajs/vue3';
import { formatPrice } from '../format';

defineProps({
    listing: { type: Object, required: true },
    // false for homes that are no longer available: same card, nothing to click.
    linked: { type: Boolean, default: true },
    // Under a results h2 the cards are h3.
    headingTag: { type: String, default: 'h2' },
});
</script>

<template>
    <article
        class="relative overflow-hidden rounded-card border border-line bg-surface shadow-card transition focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-brand-600"
        :class="{ 'hover:shadow-md': linked }"
    >
        <!-- Decorative: the address below already says what the photo shows. -->
        <img
            :src="listing.image_url"
            alt=""
            loading="lazy"
            decoding="async"
            class="aspect-card w-full object-cover"
        />

        <div class="p-4">
            <!--
                The whole card is clickable, but the anchor is what carries the
                interaction so it stays keyboard-reachable and announces properly.
                The stretched ::after makes the full card the hit area.
            -->
            <component :is="headingTag">
            <component
                :is="linked ? Link : 'div'"
                v-bind="linked ? { href: `/listings/${listing.id}` } : {}"
                class="block focus:outline-none"
                :class="{ 'after:absolute after:inset-0': linked }"
            >
                <span class="block text-xl font-bold text-ink">{{ formatPrice(listing.price) }}</span>

                <span class="mt-1 block text-ink">
                    {{ listing.address_line_1 }}, {{ listing.city }}
                </span>
            </component>
            </component>

            <p class="mt-1 text-sm text-muted">
                {{ listing.bedrooms }} bed &middot; {{ listing.bathrooms }} bath &middot;
                {{ listing.property_type_label }}
            </p>

            <p class="mt-3 text-xs text-muted">
                {{ listing.branch.name }} — {{ listing.branch.region }}
            </p>

            <slot />
        </div>
    </article>
</template>
