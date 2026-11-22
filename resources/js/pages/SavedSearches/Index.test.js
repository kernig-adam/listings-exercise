import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { axeViolations } from '../../test/axe';
import { paged } from '../../test/fixtures';
import { page, resetPage, router } from '../../test/inertia';
import Index from './Index.vue';

vi.mock('@inertiajs/vue3', async () => (await import('../../test/inertia.js')).inertiaMock);

const search = (id, description) => ({
    id,
    description,
    criteria: {},
    view_url: `/?region=${id}`,
    created_at: '2026-03-01T10:00:00+00:00',
});

const searches = [search(1, 'Leeds · Any price'), search(2, 'Liverpool & Leeds · Terraced · Up to £300,000')];

const render = (data = searches, extra = {}) =>
    mount(Index, {
        props: { savedSearches: paged(data), savedSearchCount: data.length, savedSearchLimit: 20, ...extra },
        attachTo: document.body,
    });

beforeEach(() => resetPage());

describe('Saved searches page', () => {
    it('shows how many of the limit are used', () => {
        // Arrange / Act
        const wrapper = render();

        // Assert
        expect(wrapper.text()).toContain('2 of 20 saved');
    });

    it('lists each search as chips with a View homes link', () => {
        // Arrange / Act
        const wrapper = render();

        // Assert
        const items = wrapper.findAll('article');
        expect(items).toHaveLength(2);
        expect(items[1].findAll('li').map((li) => li.text())).toEqual(['Liverpool & Leeds', 'Terraced', 'Up to £300,000']);
        expect(items[0].get('a').attributes('href')).toBe('/?region=1');
        expect(items[0].get('a').text()).toBe('View homes');
    });

    it('asks for confirmation inline before deleting, and Cancel backs out', async () => {
        // Arrange
        const wrapper = render();

        // Act
        await wrapper.get('#delete-1').trigger('click');
        const asked = wrapper.text().includes('Delete this search?');
        const noRequestYet = router.delete.mock.calls.length === 0;
        await wrapper.findAll('button').find((b) => b.text() === 'Cancel').trigger('click');

        // Assert
        expect(asked).toBe(true);
        expect(noRequestYet).toBe(true);
        expect(wrapper.text()).not.toContain('Delete this search?');
        expect(document.activeElement.id).toBe('delete-1');
    });

    it('Escape cancels the confirmation and returns focus to the delete button', async () => {
        // Arrange
        const wrapper = render();
        await wrapper.get('#delete-1').trigger('click');

        // Act
        await wrapper.get('[role="group"]').trigger('keydown', { key: 'Escape' });
        await flushPromises();

        // Assert
        expect(wrapper.text()).not.toContain('Delete this search?');
        expect(document.activeElement.id).toBe('delete-1');
    });

    it('deletes on confirm, then moves focus to the next search', async () => {
        // Arrange
        const wrapper = render();
        router.delete.mockImplementation(async (_url, opts) => {
            await wrapper.setProps({ savedSearches: paged([searches[1]]) });
            await opts.onSuccess();
        });

        // Act
        await wrapper.get('#delete-1').trigger('click');
        await wrapper.findAll('button').find((b) => b.text() === 'Delete').trigger('click');
        await flushPromises();

        // Assert
        expect(router.delete).toHaveBeenCalledWith('/saved-searches/1', expect.any(Object));
        expect(document.activeElement.getAttribute('href')).toBe('/?region=2');
    });

    it('moves focus to the heading when the last search is deleted', async () => {
        // Arrange
        const wrapper = render([searches[0]]);
        router.delete.mockImplementation(async (_url, opts) => {
            await wrapper.setProps({ savedSearches: paged([]), savedSearchCount: 0 });
            await opts.onSuccess();
        });

        // Act
        await wrapper.get('#delete-1').trigger('click');
        await wrapper.findAll('button').find((b) => b.text() === 'Delete').trigger('click');
        await flushPromises();

        // Assert
        expect(document.activeElement.textContent.trim()).toBe('No saved searches yet');
    });

    it('announces the flash message in a status region', () => {
        // Arrange
        page.props.flash.status = 'Search deleted.';

        // Act
        const wrapper = render();

        // Assert
        expect(wrapper.get('[role="status"]').text()).toBe('Search deleted.');
    });

    it('explains saved searches when there are none, with a way to make one', () => {
        // Arrange / Act
        const wrapper = render([]);

        // Assert
        expect(wrapper.get('h2').text()).toBe('No saved searches yet');
        expect(wrapper.get('main a[href="/"]').text()).toContain('Find homes');
    });

    it.each([['with searches'], ['empty']])('has no axe violations %s', async (name) => {
        // Arrange
        render(name === 'empty' ? [] : searches);
        await vi.waitFor(() => document.querySelector('h1'));

        // Act
        const violations = await axeViolations();

        // Assert
        expect(violations).toEqual([]);
    });
});
