import { h, reactive } from 'vue';
import { vi } from 'vitest';

// One fake of @inertiajs/vue3 for every component test. Each test sets page.props / page.url.
export const page = reactive({ url: '/', props: {} });
export const router = { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() };

export function resetPage(props = {}) {
    page.url = '/';
    page.props = { auth: { user: { name: 'Alex Morgan' } }, alerts: { unread: 0 }, flash: { status: null }, errors: {}, ...props };
    Object.values(router).forEach((fn) => fn.mockReset());
}

export const inertiaMock = {
    usePage: () => page,
    router,
    Head: () => null,
    Link: (props, { slots }) => h('a', props, slots.default?.()),
};
