import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { h } from 'vue';
import { axeViolations } from '../test/axe';
import ListingCard from './ListingCard.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: (props, { slots, attrs }) => h('a', { href: props.href, class: attrs.class }, slots.default?.()),
}));

const listing = {
    id: 7,
    price: 250000,
    address_line_1: '12 Park Lane',
    city: 'Leeds',
    bedrooms: 3,
    bathrooms: 2,
    property_type_label: 'Terraced',
    image_url: '/img/7.jpg',
    branch: { name: 'Leeds Central', region: 'Yorkshire' },
};

const render = (props = {}) => mount(ListingCard, { props: { listing, ...props }, attachTo: document.body });

describe('ListingCard', () => {
    it('links to the listing and shows its details', () => {
        // Arrange / Act
        const wrapper = render();

        // Assert
        expect(wrapper.get('a').attributes('href')).toBe('/listings/7');
        expect(wrapper.get('h2').text()).toContain('12 Park Lane, Leeds');
        expect(wrapper.text()).toContain('3 bed');
    });

    it('has no link when the home is unavailable', () => {
        // Arrange / Act
        const wrapper = render({ linked: false });

        // Assert
        expect(wrapper.find('a').exists()).toBe(false);
        expect(wrapper.get('h2').text()).toContain('12 Park Lane');
    });

    it('lazy-loads a decorative photo', () => {
        // Arrange / Act
        const img = render().get('img');

        // Assert
        expect(img.attributes('alt')).toBe('');
        expect(img.attributes('loading')).toBe('lazy');
        expect(img.attributes('src')).toBe('/img/7.jpg');
    });

    it.each([[true], [false]])('has no axe violations (linked: %s)', async (linked) => {
        // Arrange
        const wrapper = render({ linked });

        // Act
        const violations = await axeViolations(wrapper);

        // Assert
        expect(violations).toEqual([]);
    });
});
