<script setup>
import { computed, nextTick, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { toQuery } from '../query';

const props = defineProps({
    // The APPLIED filters from the URL, never the panel's unsubmitted state (P2).
    filters: { type: Object, required: true },
    saved: { type: Boolean, default: false },
});

const page = usePage();
const saving = ref(false);

const applied = computed(() => Object.keys(toQuery(props.filters)).length > 0);
const error = computed(() => page.props.errors?.criteria ?? null);

function save() {
    router.post('/saved-searches', toQuery(props.filters), {
        preserveScroll: true,
        onStart: () => (saving.value = true),
        onFinish: () => (saving.value = false),
        // The Save button the user just pressed is replaced by the saved line, so
        // move focus to its link rather than letting it fall back to <body>.
        onSuccess: async () => {
            await nextTick();
            document.getElementById('saved-search-link')?.focus();
        },
    });
}
</script>

<template>
    <!-- No summary text: the filter card above already shows what's applied. -->
    <div class="sm:text-right">
        <!-- One line once saved (straight after saving, or on return): the confirmation and where to find it.
             In a polite live region so the change from the button is announced. -->
        <p v-if="saved" aria-live="polite" class="inline-flex min-h-11 flex-wrap items-center gap-x-2 text-sm">
            <span class="font-semibold text-on-track"><span aria-hidden="true">&check;&nbsp;</span>Search criteria saved</span>
            <Link id="saved-search-link" href="/saved-searches" class="text-link">View Saved Searches</Link>
        </p>
        <template v-else>
            <button
                type="button"
                class="btn-secondary"
                :disabled="!applied || saving"
                :aria-describedby="error ? 'save-error' : applied ? null : 'save-hint'"
                @click="save"
            >
                {{ saving ? 'Saving…' : 'Save this search' }}
            </button>
            <!-- Visible text, because a disabled button can't take focus to explain itself. -->
            <p v-if="!applied" id="save-hint" class="mt-1 text-sm text-muted">
                Choose a filter and press Search, then you can save it.
            </p>
        </template>

        <div aria-live="polite">
            <p v-if="error" id="save-error" class="mt-2 rounded-control bg-behind-soft px-3 py-1 text-sm text-behind">
                {{ error }}
            </p>
        </div>
    </div>
</template>
