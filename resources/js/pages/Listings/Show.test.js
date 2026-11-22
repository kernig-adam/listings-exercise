import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { axeViolations } from '../../test/axe';
import { listing } from '../../test/fixtures';
import { resetPage } from '../../test/inertia';
import Show from './Show.vue';

vi.mock('@inertiajs/vue3', async () => (await import('../../test/inertia.js')).inertiaMock);

const render = () =>
    mount(Show, {
        props: { listing: listing(5, { listed_at: '2026-03-01T10:00:00+00:00', reference: 'REF-5' }) },
        attachTo: document.body,
    });

beforeEach(() => {
    resetPage();
    sessionStorage.clear();
});

describe('Listing detail page', () => {
    it('links back to the buyer\'s filtered results and page', () => {
        // Arrange
        sessionStorage.setItem('listingsUrl', '/?region=Leeds&page=2');

        // Act
        const wrapper = render();

        // Assert
        expect(wrapper.get('a[href="/?region=Leeds&page=2"]').text()).toContain('Back to all listings');
    });

    it('falls back to the plain listings page when there is no history', () => {
        // Arrange / Act
        const wrapper = render();

        // Assert
        expect(wrapper.get('main a[href="/"]').text()).toContain('Back to all listings');
    });

    it('has no axe violations', async () => {
        // Arrange
        render();

        // Act
        const violations = await axeViolations();

        // Assert
        expect(violations).toEqual([]);
    });
});
