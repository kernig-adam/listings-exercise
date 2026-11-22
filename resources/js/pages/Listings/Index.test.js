import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { axeViolations } from '../../test/axe';
import { listing, noFilters, options, paged } from '../../test/fixtures';
import { page, resetPage, router } from '../../test/inertia';
import Index from './Index.vue';

vi.mock('@inertiajs/vue3', async () => (await import('../../test/inertia.js')).inertiaMock);

const render = (props = {}) =>
    mount(Index, {
        props: {
            listings: paged([listing(1), listing(2)]),
            filters: noFilters,
            currentSearchSaved: false,
            ...options,
            ...props,
        },
        attachTo: document.body,
    });

beforeEach(() => {
    resetPage();
    sessionStorage.clear();
});

describe('Listings page', () => {
    it('shows the filters card, the count and one card per home under the results heading', () => {
        // Arrange / Act
        const wrapper = render();

        // Assert
        expect(wrapper.get('search').text()).toContain('Find your home');
        expect(wrapper.get('#results-heading').text()).toBe('2 homes found');
        expect(wrapper.findAll('#results article')).toHaveLength(2);
        expect(wrapper.findAll('h1')).toHaveLength(1);
    });

    it('searches with the query from the filters and keeps the result in the URL', async () => {
        // Arrange
        const wrapper = render();

        // Act
        await wrapper.get('input[value="Leeds"]').setValue(true);
        await wrapper.get('form').trigger('submit');

        // Assert
        expect(router.get).toHaveBeenCalledWith('/', { region: 'Leeds' }, expect.objectContaining({ replace: true }));
    });

    it('remembers its URL so a listing can link back to the same results', () => {
        // Arrange
        page.url = '/?region=Leeds&page=2';

        // Act
        render();

        // Assert
        expect(sessionStorage.getItem('listingsUrl')).toBe('/?region=Leeds&page=2');
    });

    it('offers to save the applied search next to the count', () => {
        // Arrange / Act
        const wrapper = render({
            filters: { ...noFilters, region: ['Leeds'] },
        });

        // Assert
        expect(wrapper.get('#results h2 + div button').text()).toBe('Save this search');
    });

    it('explains an empty result and still offers to save it', () => {
        // Arrange / Act
        const wrapper = render({
            listings: paged([]),
            filters: { ...noFilters, region: ['Leeds'] },
        });

        // Assert
        expect(wrapper.text()).toContain("No homes match yet. Save this search and we'll add new matching homes to your Alerts.");
        expect(wrapper.findAll('#results button')).toHaveLength(1);
    });

    it('shows a field error from a bad link next to its field', () => {
        // Arrange
        page.props.errors = { region: 'Choose a valid region.' };

        // Act
        const wrapper = render();

        // Assert
        expect(wrapper.get('#region-error').text()).toBe('Choose a valid region.');
    });

    it.each([
        ['with results', {}],
        ['filtered and empty', { listings: paged([]), filters: { ...noFilters, region: ['Leeds'] } }],
        ['unfiltered and empty', { listings: paged([]) }],
    ])('has no axe violations %s', async (_name, props) => {
        // Arrange
        render(props);

        // Act
        const violations = await axeViolations();

        // Assert
        expect(violations).toEqual([]);
    });
});
