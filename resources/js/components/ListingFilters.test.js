import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { axeViolations } from '../test/axe';
import { noFilters, options } from '../test/fixtures';
import ListingFilters from './ListingFilters.vue';

vi.mock('@inertiajs/vue3', async () => (await import('../test/inertia.js')).inertiaMock);

const render = (props = {}) =>
    mount(ListingFilters, { props: { filters: noFilters, ...options, ...props }, attachTo: document.body });

const chip = (wrapper, name) => wrapper.get(`input[type="checkbox"][value="${name}"]`);
const optionValues = (select) => select.findAll('option').map((o) => o.element.value);

describe('ListingFilters', () => {
    it('renders regions and types as checkbox chips inside labelled fieldsets', () => {
        // Arrange / Act
        const wrapper = render();

        // Assert
        const legends = wrapper.findAll('legend').map((l) => l.text());
        expect(legends).toEqual(['Regions', 'Property types', 'Price', 'Bedrooms', 'Bathrooms']);
        expect(wrapper.get('label:has(input[value="Leeds"])').text()).toBe('Leeds');
    });

    it('toggles chips and emits comma-separated values, omitting empties', async () => {
        // Arrange
        const wrapper = render();

        // Act
        await chip(wrapper, 'Leeds').setValue(true);
        await chip(wrapper, 'Liverpool').setValue(true);
        await chip(wrapper, 'flat').setValue(true);
        await wrapper.get('form').trigger('submit');

        // Assert
        expect(wrapper.emitted('search')[0][0]).toEqual({ region: 'Leeds,Liverpool', property_type: 'flat' });
    });

    it('shows a selected chip with a tick that is hidden from assistive tech', async () => {
        // Arrange
        const wrapper = render();

        // Act
        await chip(wrapper, 'Leeds').setValue(true);

        // Assert
        const tick = wrapper.get('label:has(input[value="Leeds"]) [aria-hidden="true"]');
        expect(tick.text()).toBe('✓');
    });

    it('hides max options below the chosen min', async () => {
        // Arrange
        const wrapper = render();

        // Act
        await wrapper.get('#min_price').setValue(200000);

        // Assert
        expect(optionValues(wrapper.get('#max_price'))).toEqual(['', '200000', '300000']);
    });

    it('clears a max that falls below a newly chosen min', async () => {
        // Arrange
        const wrapper = render({ filters: { ...noFilters, max_price: 100000 } });

        // Act
        await wrapper.get('#min_price').setValue(300000);
        await wrapper.get('form').trigger('submit');

        // Assert
        expect(wrapper.emitted('search')[0][0]).toEqual({ min_price: 300000 });
    });

    it('shows a non-preset value from the URL as an extra selected option', () => {
        // Arrange / Act
        const wrapper = render({ filters: { ...noFilters, max_price: 180000 } });

        // Assert
        const select = wrapper.get('#max_price');
        expect(optionValues(select)).toContain('180000');
        expect(select.element.value).toBe('180000');
    });

    it('links a field error to its field', () => {
        // Arrange / Act
        const wrapper = render({ errors: { region: 'Choose a valid region.' } });

        // Assert
        const group = wrapper.findAll('fieldset')[0];
        const error = wrapper.get(`#${group.attributes('aria-describedby')}`);
        expect(error.text()).toBe('Choose a valid region.');
    });

    it('clears everything and searches with no filters', async () => {
        // Arrange
        const wrapper = render({ filters: { ...noFilters, region: ['Leeds'] } });

        // Act
        await wrapper.get('button[type="button"]').trigger('click');

        // Assert
        expect(wrapper.emitted('search')[0][0]).toEqual({});
        expect(chip(wrapper, 'Leeds').element.checked).toBe(false);
    });

    it('puts Clear filters before Search so tab order matches the right-aligned footer', () => {
        // Arrange / Act
        const wrapper = render({ filters: { ...noFilters, region: ['Leeds'] } });

        // Assert
        const buttons = wrapper.findAll('form button').map((b) => b.text());
        expect(buttons).toEqual(['Clear filters', 'Search']);
    });

    it('has no axe violations, with an error showing', async () => {
        // Arrange
        render({ errors: { min_price: 'Too high.' } });

        // Act
        const violations = await axeViolations();

        // Assert
        expect(violations).toEqual([]);
    });
});
