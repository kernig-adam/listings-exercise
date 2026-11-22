import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { axeViolations } from '../../test/axe';
import { listing, paged } from '../../test/fixtures';
import { resetPage, router } from '../../test/inertia';
import Index from './Index.vue';

vi.mock('@inertiajs/vue3', async () => (await import('../../test/inertia.js')).inertiaMock);

const alert = (id, extra = {}) => ({
    id,
    reason: 'new_listing',
    reason_label: 'New on the market',
    is_new: false,
    first_alerted_at: '2026-03-01T10:00:00+00:00',
    alert_count: 1,
    alerted_at: '2026-03-01T10:00:00+00:00',
    listing: listing(id),
    is_available: true,
    matched: ['Leeds · Any price'],
    matched_deleted: false,
    ...extra,
});

const render = (data) => mount(Index, { props: { alertList: paged(data) }, attachTo: document.body });

beforeEach(() => resetPage());

describe('Alerts page', () => {
    it('shows a New pill and the reason label as text', () => {
        // Arrange / Act
        const wrapper = render([alert(1, { is_new: true, reason_label: 'Back on the market' }), alert(2)]);

        // Assert
        const [first, second] = wrapper.findAll('article');
        expect(first.text()).toContain('New');
        expect(first.text()).toContain('Back on the market');
        expect(second.text()).not.toMatch(/\bNew\b(?! on)/);
    });

    it('says which search matched, or that it has since been deleted', () => {
        // Arrange / Act
        const wrapper = render([alert(1), alert(2, { matched: [], matched_deleted: true })]);

        // Assert
        const [first, second] = wrapper.findAll('article');
        expect(first.text()).toContain('Matched: Leeds · Any price');
        expect(second.text()).toContain("Matched a search you've since deleted");
    });

    it('shows when it was first alerted only for a repeat alert', () => {
        // Arrange / Act
        const wrapper = render([alert(1, { alert_count: 3 }), alert(2)]);

        // Assert
        const [first, second] = wrapper.findAll('article');
        expect(first.get('time').attributes('datetime')).toBe('2026-03-01T10:00:00+00:00');
        expect(first.text()).toContain('First alerted 1 March 2026');
        expect(second.text()).not.toContain('First alerted');
    });

    it('links an available home but not an unavailable one, which says so in text', () => {
        // Arrange / Act
        const wrapper = render([alert(1), alert(2, { is_available: false })]);

        // Assert
        const [first, second] = wrapper.findAll('article');
        expect(first.get('a').attributes('href')).toBe('/listings/1?from=alert');
        expect(second.find('a').exists()).toBe(false);
        expect(second.text()).toContain('No longer available');
    });

    it('marks the shown alerts read once, refreshing only the unread count', () => {
        // Arrange / Act
        render([alert(1, { is_new: true }), alert(2)]);

        // Assert
        expect(router.patch).toHaveBeenCalledTimes(1);
        expect(router.patch).toHaveBeenCalledWith(
            '/alerts',
            { alert_ids: [1, 2], read: true },
            { preserveState: true, preserveScroll: true, only: ['alerts'] },
        );
    });

    it('sends nothing when nothing is new', () => {
        // Arrange / Act
        render([alert(1), alert(2)]);

        // Assert
        expect(router.patch).not.toHaveBeenCalled();
    });

    it('explains alerts when there are none, with a way to save a search', () => {
        // Arrange / Act
        const wrapper = render([]);

        // Assert
        expect(wrapper.get('h2').text()).toBe('No alerts yet');
        expect(wrapper.get('main a[href="/"]').text()).toContain('Save a search');
    });

    it.each([
        ['with alerts', () => [alert(1, { is_new: true }), alert(2, { is_available: false, matched_deleted: true, matched: [] })]],
        ['empty', () => []],
    ])('has no axe violations %s', async (_name, data) => {
        // Arrange
        render(data());

        // Act
        const violations = await axeViolations();

        // Assert
        expect(violations).toEqual([]);
    });
});
