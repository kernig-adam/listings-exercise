<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import IconTooltip from './IconTooltip.vue';
import logo from '../../images/street-logo.svg?raw';

defineProps({
    heading: { type: String, required: true },
    subheading: { type: String, default: null },
});

const page = usePage();

const unread = computed(() => page.props.alerts?.unread ?? 0);
const user = computed(() => page.props.auth?.user ?? null);
const initials = computed(() =>
    (user.value?.name ?? '')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0].toUpperCase())
        .join(''),
);

// Compare the path only, so a filtered /?city=Leeds still counts as Listings.
const path = computed(() => page.url.split('?')[0]);
const listingsCurrent = computed(() => path.value === '/' || path.value.startsWith('/listings'));
const savedCurrent = computed(() => path.value.startsWith('/saved-searches'));
const alertsLabel = computed(() => (unread.value ? `Alerts, ${unread.value} unread` : 'Alerts'));
const alertsCurrent = computed(() => path.value.startsWith('/alerts'));
</script>

<template>
    <a
        href="#main"
        class="sr-only focus:not-sr-only focus:fixed focus:left-2 focus:top-2 focus:z-30 focus:bg-surface focus:px-4 focus:py-2 focus:text-ink focus-ring"
    >
        Skip to main content
    </a>

    <!--
        Sticky so it floats like street.co.uk. It is 68px tall (44px icons + py-3), and <html> has
        scroll-pt-20 (80px) so a focused element or the #main skip target never lands underneath it (WCAG 2.4.11).
    -->
    <header class="sticky top-0 z-20 bg-navy text-surface shadow-card">
        <div class="mx-auto flex max-w-7xl items-center gap-x-4 px-4 py-3 sm:px-6 lg:px-8">
            <Link href="/" aria-label="Street" class="shrink-0 focus-ring-inverse">
                <span class="logo-inverse block" aria-hidden="true" v-html="logo" />
            </Link>

            <!-- Below sm the logo (which links home = Listings) covers this link, so no menu button is needed. -->
            <nav aria-label="Main" class="hidden sm:block">
                <Link
                    href="/"
                    class="inline-flex min-h-11 items-center text-sm font-medium underline-offset-8 hover:underline focus-ring-inverse"
                    :class="listingsCurrent ? 'underline' : 'text-navy-muted'"
                    :aria-current="listingsCurrent ? 'page' : null"
                >
                    Listings
                </Link>
            </nav>

            <div class="ml-auto flex items-center gap-1 sm:gap-3">
                <IconTooltip text="Saved searches">
                <Link
                    href="/saved-searches"
                    class="inline-flex size-11 items-center justify-center rounded-pill focus-ring-inverse"
                    :class="savedCurrent ? 'bg-brand-800' : 'hover:bg-brand-900'"
                    :aria-current="savedCurrent ? 'page' : null"
                    aria-label="Saved searches"
                >
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z"
                        />
                    </svg>
                </Link>
                </IconTooltip>

                <!-- Prefetching is safe: viewing alerts has no side effects (marking read is an explicit PATCH). -->
                <IconTooltip :text="alertsLabel">
                <Link
                    href="/alerts"
                    prefetch
                    class="relative inline-flex size-11 items-center justify-center rounded-pill focus-ring-inverse"
                    :class="alertsCurrent ? 'bg-brand-800' : 'hover:bg-brand-900'"
                    :aria-current="alertsCurrent ? 'page' : null"
                    :aria-label="alertsLabel"
                >
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"
                        />
                    </svg>
                    <span
                        v-if="unread"
                        class="absolute right-0 top-0 min-w-5 rounded-pill bg-surface px-1 text-center text-xs font-bold text-navy"
                        aria-hidden="true"
                    >
                        {{ unread }}
                    </span>
                </Link>
                </IconTooltip>

                <!-- The name is not shown, so the avatar carries it for assistive tech; the initials stay hidden so they aren't read as "A M". -->
                <IconTooltip v-if="user" :text="`Signed in as ${user.name}`">
                <span class="flex items-center">
                    <span class="sr-only">Signed in as {{ user.name }}</span>
                    <span
                        class="flex size-8 items-center justify-center rounded-pill bg-brand-100 text-xs font-bold text-brand-900"
                        aria-hidden="true"
                    >
                        {{ initials }}
                    </span>
                </span>
                </IconTooltip>
            </div>
        </div>
    </header>

    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <main id="main" tabindex="-1" class="focus:outline-none">
            <h1 tabindex="-1" class="text-3xl focus:outline-none font-bold tracking-tight text-ink">{{ heading }}</h1>
            <p v-if="subheading" class="mt-1 text-muted">{{ subheading }}</p>

            <div class="mt-8">
                <slot />
            </div>
        </main>
    </div>
</template>
