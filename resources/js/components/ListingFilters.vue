<script setup>
import { computed, ref, watch } from 'vue';
import { toQuery } from '../query';

const props = defineProps({
    // The applied filters (from the URL), the single source of truth.
    filters: { type: Object, required: true },
    propertyTypes: { type: Array, required: true },
    regionOptions: { type: Array, required: true },
    priceOptions: { type: Array, required: true },
    bedroomOptions: { type: Array, required: true },
    bathroomOptions: { type: Array, required: true },
    errors: { type: Object, default: () => ({}) },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(['search']);

const blank = {
    region: [],
    property_type: [],
    min_price: '',
    max_price: '',
    min_bedrooms: '',
    max_bedrooms: '',
    min_bathrooms: '',
    max_bathrooms: '',
};

function fromFilters(filters) {
    return Object.fromEntries(
        Object.entries(blank).map(([key, empty]) => [key, filters[key] ?? empty]),
    );
}

// Seeded from the server so the panel survives a refresh, a shared link or the back button.
const form = ref(fromFilters(props.filters));
watch(() => props.filters, (filters) => (form.value = fromFilters(filters)), { deep: true });

// The chosen max can't sit below the chosen min: drop it rather than send a search that fails.
for (const [min, max] of [['min_price', 'max_price'], ['min_bedrooms', 'max_bedrooms'], ['min_bathrooms', 'max_bathrooms']]) {
    watch(() => form.value[min], (value) => {
        if (value !== '' && form.value[max] !== '' && form.value[max] < value) {
            form.value[max] = '';
        }
    });
}

/**
 * Preset options for one select. P3: a value that isn't a preset (e.g. 180000
 * from a shared link) is added so the select shows what is really applied.
 * A max select hides everything below the chosen min.
 */
function optionsFor(presets, current, floor = '') {
    const all = [...presets];

    if (current !== '' && current !== null && !presets.some((option) => option.value === current)) {
        all.push({ value: current, label: String(current) });
        all.sort((a, b) => a.value - b.value);
    }

    return floor === '' ? all : all.filter((option) => option.value >= floor || option.value === current);
}

const ranges = computed(() => [
    { key: 'price', legend: 'Price', presets: props.priceOptions },
    { key: 'bedrooms', legend: 'Bedrooms', presets: props.bedroomOptions },
    { key: 'bathrooms', legend: 'Bathrooms', presets: props.bathroomOptions },
].map((range) => ({
    ...range,
    min: { name: `min_${range.key}`, label: `Min ${range.key}`, empty: 'No min', options: optionsFor(range.presets, form.value[`min_${range.key}`]) },
    max: { name: `max_${range.key}`, label: `Max ${range.key}`, empty: 'No max', options: optionsFor(range.presets, form.value[`max_${range.key}`], form.value[`min_${range.key}`]) },
})));

const chipGroups = computed(() => [
    { key: 'region', legend: 'Regions', options: props.regionOptions.map((region) => ({ value: region, label: region })) },
    { key: 'property_type', legend: 'Property types', options: props.propertyTypes },
]);

const hasAnything = computed(() => Object.keys(toQuery(form.value)).length > 0);

function submit() {
    emit('search', toQuery(form.value));
}

function clear() {
    form.value = fromFilters({});
    emit('search', {});
}

const selectClasses = 'min-h-11 w-full rounded-control border border-line bg-surface px-3 text-sm text-ink focus-ring-inverse';
</script>

<template>
    <search class="block rounded-card bg-navy p-5 text-surface shadow-card sm:p-8">
        <h2 class="text-xl font-bold">Find your home</h2>

        <form class="mt-5 grid gap-6 md:grid-cols-2" @submit.prevent="submit">
            <fieldset
                v-for="group in chipGroups"
                :key="group.key"
                :aria-describedby="errors[group.key] ? `${group.key}-error` : null"
            >
                <legend class="mb-2 text-sm font-semibold">{{ group.legend }}</legend>

                <ul class="flex flex-wrap gap-2">
                    <li v-for="option in group.options" :key="option.value">
                        <label
                            class="chip-focus inline-flex min-h-11 cursor-pointer items-center gap-1 rounded-pill border px-4 text-sm font-medium"
                            :class="form[group.key].includes(option.value) ? 'border-brand-100 bg-brand-100 text-brand-900' : 'border-navy-muted text-surface hover:bg-brand-900'"
                        >
                            <input v-model="form[group.key]" type="checkbox" :value="option.value" class="sr-only" />
                            <span v-if="form[group.key].includes(option.value)" aria-hidden="true">&check;</span>
                            {{ option.label }}
                        </label>
                    </li>
                </ul>

                <p
                    v-if="errors[group.key]"
                    :id="`${group.key}-error`"
                    class="mt-2 rounded-control bg-behind-soft px-3 py-1 text-sm text-behind"
                >
                    {{ errors[group.key] }}
                </p>
            </fieldset>

            <!-- Price | Bedrooms | Bathrooms: one column each from lg, stacked below so the min/max selects stay readable. -->
            <div class="grid gap-6 md:col-span-2 lg:grid-cols-3">
            <fieldset v-for="range in ranges" :key="range.key">
                <legend class="mb-2 text-sm font-semibold">{{ range.legend }}</legend>

                <div class="grid grid-cols-2 gap-3">
                    <div v-for="field in [range.min, range.max]" :key="field.name">
                        <label :for="field.name" class="mb-1 block text-sm">{{ field.label }}</label>
                        <select
                            :id="field.name"
                            v-model="form[field.name]"
                            :class="selectClasses"
                            :aria-invalid="errors[field.name] ? 'true' : null"
                            :aria-describedby="errors[field.name] ? `${field.name}-error` : null"
                        >
                            <option value="">{{ field.empty }}</option>
                            <option v-for="option in field.options" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                        <p
                            v-if="errors[field.name]"
                            :id="`${field.name}-error`"
                            class="mt-1 rounded-control bg-behind-soft px-3 py-1 text-sm text-behind"
                        >
                            {{ errors[field.name] }}
                        </p>
                    </div>
                </div>
            </fieldset>
            </div>

            <!-- Clear before Search: the primary action is last, on the right, and tab order matches. -->
            <div class="flex flex-wrap items-center justify-end gap-4 md:col-span-2">
                <button v-if="hasAnything" type="button" class="text-link-inverse min-h-11 text-sm" @click="clear">
                    Clear filters
                </button>
                <button type="submit" :disabled="processing" class="btn-inverse">Search</button>
            </div>
        </form>
    </search>
</template>
