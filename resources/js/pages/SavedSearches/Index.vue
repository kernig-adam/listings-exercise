<script setup>
import { nextTick, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '../../components/AppLayout.vue';
import Pagination from '../../components/Pagination.vue';
import { formatDate } from '../../format';

const props = defineProps({
    savedSearches: { type: Object, required: true },
    savedSearchCount: { type: Number, required: true },
    savedSearchLimit: { type: Number, required: true },
});

const page = usePage();
const list = ref(null);
const emptyHeading = ref(null);
const confirming = ref(null);
const deleting = ref(false);

// The description is "Liverpool · Terraced · Up to £300k"; each part is one chip.
const parts = (description) => description.split(' · ');

// Back out and return focus to the button that opened the confirmation.
async function cancel(id) {
    confirming.value = null;
    await nextTick();
    document.getElementById(`delete-${id}`)?.focus();
}

function remove(search, index) {
    router.delete(`/saved-searches/${search.id}`, {
        preserveScroll: true,
        onStart: () => (deleting.value = true),
        onFinish: () => (deleting.value = false),
        onSuccess: async () => {
            confirming.value = null;
            await nextTick();
            // Focus must not fall back to <body>: go to the search now in this slot, else the heading.
            const next = list.value?.querySelectorAll('[data-view]')[index];
            (next ?? emptyHeading.value)?.focus();
        },
    });
}
</script>

<template>
    <Head title="Saved searches" />

    <AppLayout heading="Saved searches">
        <p class="text-muted">{{ savedSearchCount }} of {{ savedSearchLimit }} saved</p>

        <!-- The live region stays in the DOM so a message added to it is announced. -->
        <div role="status">
            <p v-if="page.props.flash?.status" class="mt-4 rounded-control bg-on-track-soft px-4 py-2 text-sm text-on-track">
                {{ page.props.flash.status }}
            </p>
        </div>

        <ul v-if="savedSearches.data.length" ref="list" class="mt-6 grid gap-4">
            <li v-for="(search, index) in savedSearches.data" :key="search.id">
                <article class="rounded-card border border-line bg-surface p-5 shadow-card">
                    <h2 class="sr-only">{{ search.description }}</h2>

                    <ul class="flex flex-wrap gap-2" aria-label="Search criteria">
                        <li v-for="part in parts(search.description)" :key="part" class="pill bg-brand-100 text-brand-900">
                            {{ part }}
                        </li>
                    </ul>

                    <p class="mt-3 text-sm text-muted">
                        Saved <time :datetime="search.created_at">{{ formatDate(search.created_at) }}</time>
                    </p>

                    <div class="mt-4 flex flex-wrap items-center gap-4">
                        <Link :href="search.view_url" data-view class="btn-primary">View homes</Link>

                        <button
                            v-if="confirming !== search.id"
                            :id="`delete-${search.id}`"
                            type="button"
                            class="text-link min-h-11 text-sm"
                            :aria-label="`Delete search: ${search.description}`"
                            @click="confirming = search.id"
                        >
                            Delete search
                        </button>

                        <div
                            v-else
                            role="group"
                            :aria-label="`Confirm deleting: ${search.description}`"
                            class="flex flex-wrap items-center gap-3"
                            @keydown.esc="cancel(search.id)"
                        >
                            <p class="text-sm font-semibold text-ink">Delete this search?</p>
                            <button type="button" class="btn-primary" :disabled="deleting" @click="remove(search, index)">
                                Delete
                            </button>
                            <button type="button" class="btn-secondary" @click="cancel(search.id)">Cancel</button>
                        </div>
                    </div>
                </article>
            </li>
        </ul>

        <div v-else class="mt-6 rounded-card border border-dashed border-line bg-surface p-8 text-center">
            <h2 ref="emptyHeading" tabindex="-1" class="text-xl font-bold text-ink focus:outline-none">
                No saved searches yet
            </h2>
            <p class="mx-auto mt-2 max-w-prose text-muted">
                Save a search and we'll add new matching homes to your Alerts, so you don't have to keep checking.
            </p>
            <Link href="/" class="btn-primary mt-5">Find homes to search</Link>
        </div>

        <Pagination :meta="savedSearches.meta" :links="savedSearches.links" />
    </AppLayout>
</template>
