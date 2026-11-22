<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';

defineProps({
    text: { type: String, required: true },
});

/*
 * The tooltip is a descendant of the wrapper, so moving the pointer onto it keeps the hover
 * (hoverable). It is aria-hidden: the wrapped control already carries its accessible name
 * (aria-label), so a screen reader isn't told twice. pt-1 (not a margin) bridges the gap so the
 * pointer never leaves in between.
 */

// Hover and keyboard focus are tracked separately so leaving one doesn't hide a tooltip the other still wants.
const hovered = ref(false);
const focused = ref(false);
const dismissed = ref(false);
const visible = () => (hovered.value || focused.value) && !dismissed.value;

// Dismissal lasts until both hover and focus have left, so the tooltip can show again next time.
function leave() {
    if (!hovered.value && !focused.value) {
        dismissed.value = false;
    }
}

// Escape hides it without moving the pointer or focus (WCAG 1.4.13, dismissible). Listening on the
// document means it works for a hover-only tooltip too, where no element has focus.
function onKeydown(event) {
    if (event.key === 'Escape') {
        dismissed.value = true;
    }
}

watch([hovered, focused], ([isHovered, isFocused]) => {
    if (isHovered || isFocused) {
        document.addEventListener('keydown', onKeydown);
    } else {
        document.removeEventListener('keydown', onKeydown);
    }
});
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
    <span
        class="relative inline-flex"
        @mouseenter="hovered = true"
        @mouseleave="hovered = false; leave()"
        @focusin="focused = true"
        @focusout="focused = false; leave()"
    >
        <slot />
        <span v-show="visible()" class="absolute right-0 top-full z-10 pt-1" aria-hidden="true">
            <span class="block whitespace-nowrap rounded-control border border-line bg-surface px-2 py-1 text-xs font-medium text-ink shadow-card">
                {{ text }}
            </span>
        </span>
    </span>
</template>
