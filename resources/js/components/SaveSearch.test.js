import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { axeViolations } from '../test/axe';
import { page, resetPage, router } from '../test/inertia';
import { noFilters } from '../test/fixtures';
import SaveSearch from './SaveSearch.vue';

vi.mock('@inertiajs/vue3', async () => (await import('../test/inertia.js')).inertiaMock);

const applied = { ...noFilters, region: ['Leeds', 'Liverpool'], max_price: 300000 };
const render = (props = {}) =>
    mount(SaveSearch, {
        props: { filters: applied, ...props },
        attachTo: document.body,
    });
const button = (wrapper) => wrapper.get('button');

beforeEach(() => resetPage());

describe('SaveSearch', () => {
    it('saves the applied filters, comma-separated, without repeating them as text', async () => {
        // Arrange
        const wrapper = render();

        // Act
        await button(wrapper).trigger('click');

        // Assert
        expect(wrapper.text()).not.toContain('Leeds');
        expect(router.post).toHaveBeenCalledWith(
            '/saved-searches',
            { region: 'Leeds,Liverpool', max_price: 300000 },
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('is disabled with a visible hint when nothing is applied', () => {
        // Arrange / Act
        const wrapper = render({ filters: noFilters });

        // Assert
        expect(button(wrapper).element.disabled).toBe(true);
        const hint = wrapper.get(`#${button(wrapper).attributes('aria-describedby')}`);
        expect(hint.text()).toContain('Choose a filter');
    });

    it('shows one saved line, with no button, when this search exists', () => {
        // Arrange / Act
        const wrapper = render({ saved: true });

        // Assert
        expect(wrapper.find('button').exists()).toBe(false);
        expect(wrapper.text()).toContain('Search criteria saved');
        expect(wrapper.text()).not.toContain('Already saved');
        expect(wrapper.get('[aria-hidden="true"]').text()).toContain('✓');
    });

    it('disables the button while saving', async () => {
        // Arrange
        router.post.mockImplementation((_url, _data, opts) => opts.onStart());
        const wrapper = render();

        // Act
        await button(wrapper).trigger('click');

        // Assert
        expect(button(wrapper).element.disabled).toBe(true);
        expect(button(wrapper).text()).toBe('Saving…');
    });

    it('announces the saved state in a polite live region, as one line with a link to saved searches', () => {
        // Arrange
        page.props.flash.status = 'Search saved.';

        // Act
        const wrapper = render({ saved: true });

        // Assert
        const regions = wrapper.findAll('[aria-live="polite"]').filter((r) => r.text());
        expect(regions).toHaveLength(1);
        expect(regions[0].text()).toContain('Search criteria saved');
        expect(regions[0].get('a').text()).toBe('View Saved Searches');
        expect(regions[0].get('a').attributes('href')).toBe('/saved-searches');
        expect(wrapper.text()).not.toContain('Search saved.');
    });

    it('moves focus to "View Saved Searches" after saving, instead of losing it to the page', async () => {
        // Arrange
        const wrapper = render();
        router.post.mockImplementation(async (_url, _data, opts) => {
            // The server answers: the page now says this search is saved.
            await wrapper.setProps({ saved: true });
            await opts.onSuccess();
        });

        // Act
        await button(wrapper).trigger('click');

        // Assert
        expect(document.activeElement?.textContent).toBe('View Saved Searches');
    });

    it('shows the error next to the button and ties it with aria-describedby', () => {
        // Arrange
        page.props.errors = { criteria: 'You already have this search saved.' };

        // Act
        const wrapper = render();

        // Assert
        expect(button(wrapper).attributes('aria-describedby')).toBe('save-error');
        expect(wrapper.get('#save-error').text()).toBe('You already have this search saved.');
    });

    it.each([
        ['applied', {}],
        ['nothing applied', { filters: noFilters }],
        ['already saved', { saved: true }],
    ])('has no axe violations when %s', async (_name, props) => {
        // Arrange
        page.props.flash.status = 'Search saved.';
        render(props);

        // Act
        const violations = await axeViolations();

        // Assert
        expect(violations).toEqual([]);
    });
});
