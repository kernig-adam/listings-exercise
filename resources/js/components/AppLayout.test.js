import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { h, reactive } from 'vue';
import { axeViolations } from '../test/axe';
import AppLayout from './AppLayout.vue';

const page = reactive({ url: '/', props: {} });

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
    Link: (props, { slots }) => h('a', { href: props.href, 'aria-current': props['aria-current'], 'aria-label': props['aria-label'] }, slots.default?.()),
}));

function render({ url = '/', user = { name: 'Alex Morgan' }, unread = 0 } = {}) {
    page.url = url;
    page.props = { auth: { user }, alerts: { unread } };

    return mount(AppLayout, {
        props: { heading: 'Properties for sale' },
        slots: { default: '<p>Content</p>' },
        attachTo: document.body,
    });
}

describe('AppLayout', () => {
    it('links the logo home with the accessible name Street', () => {
        // Arrange / Act
        const wrapper = render();

        // Assert
        const logo = wrapper.get('a[aria-label="Street"]');
        expect(logo.attributes('href')).toBe('/');
        expect(logo.find('svg').exists()).toBe(true);
    });

    it('has a nav with only Listings, placed before the right-hand icons', () => {
        // Arrange / Act
        const wrapper = render({ url: '/?city=Leeds' });

        // Assert
        const nav = wrapper.get('nav[aria-label="Main"]');
        expect(nav.findAll('a').map((a) => a.text())).toEqual(['Listings']);
        expect(nav.get('a').attributes('aria-current')).toBe('page');
        const links = wrapper.findAll('header a');
        expect(links.map((a) => a.attributes('href'))).toEqual(['/', '/', '/saved-searches', '/alerts']);
    });

    it('shows a bookmark link named Saved searches, current on that page', () => {
        // Arrange / Act
        const wrapper = render({ url: '/saved-searches' });

        // Assert
        const link = wrapper.get('a[href="/saved-searches"]');
        expect(link.attributes('aria-label')).toBe('Saved searches');
        expect(link.attributes('aria-current')).toBe('page');
        expect(link.get('svg').attributes('aria-hidden')).toBe('true');
    });

    it('orders the right-hand cluster saved searches, alerts, avatar', () => {
        // Arrange / Act
        const wrapper = render();

        // Assert
        const cluster = wrapper.get('a[href="/saved-searches"]').element.parentElement.parentElement;
        const [saved, alerts, avatar] = [...cluster.children];
        expect(saved.querySelector('a').getAttribute('aria-label')).toBe('Saved searches');
        expect(alerts.querySelector('a').getAttribute('aria-label')).toBe('Alerts');
        expect(avatar.querySelector('.sr-only').textContent).toBe('Signed in as Alex Morgan');
        expect(avatar).toBe(cluster.lastElementChild);
    });

    it('floats the header above the content', () => {
        // Arrange / Act
        const header = render().get('header');

        // Assert
        expect(header.classes()).toEqual(expect.arrayContaining(['sticky', 'top-0', 'z-20']));
    });

    it('shows a bell link to alerts with an unread badge and accessible label', () => {
        // Arrange / Act
        const wrapper = render({ unread: 3 });

        // Assert
        const bell = wrapper.get('a[href="/alerts"]');
        expect(bell.attributes('aria-label')).toBe('Alerts, 3 unread');
        expect(bell.text()).toBe('3');
        expect(bell.get('svg').attributes('aria-hidden')).toBe('true');
    });

    it('names the bell Alerts and hides the badge when nothing is unread', () => {
        // Arrange / Act
        const bell = render({ unread: 0 }).get('a[href="/alerts"]');

        // Assert
        expect(bell.attributes('aria-label')).toBe('Alerts');
        expect(bell.text()).toBe('');
    });

    it('marks the bell as current on the alerts page', () => {
        // Arrange / Act
        const wrapper = render({ url: '/alerts' });

        // Assert
        expect(wrapper.get('a[href="/alerts"]').attributes('aria-current')).toBe('page');
        expect(wrapper.find('nav [aria-current]').exists()).toBe(false);
    });

    it('shows only an avatar for the user, named for assistive tech', () => {
        // Arrange / Act
        const wrapper = render();

        // Assert
        expect(wrapper.get('.sr-only:not(a)').text()).toBe('Signed in as Alex Morgan');
        const initials = wrapper.findAll('span[aria-hidden="true"]').find((el) => el.text() === 'AM');
        expect(initials).toBeDefined();
        expect(wrapper.text()).not.toContain('Welcome back');
    });

    it('shows nothing about the user when signed out', () => {
        // Arrange / Act
        const wrapper = render({ user: null });

        // Assert
        expect(wrapper.text()).not.toContain('Signed in as');
        expect(wrapper.text()).not.toContain('AM');
    });

    it('puts the skip link first and points it at the single main landmark', () => {
        // Arrange / Act
        const wrapper = render();

        // Assert
        const skip = wrapper.get('a');
        expect(skip.text()).toBe('Skip to main content');
        expect(skip.attributes('href')).toBe('#main');
        expect(wrapper.get('main').attributes('id')).toBe('main');
        expect(wrapper.findAll('h1')).toHaveLength(1);
        expect(wrapper.get('h1').text()).toBe('Properties for sale');
    });

    it.each([
        ['with user and unread alerts', { unread: 4 }],
        ['without a user', { user: null }],
    ])('has no axe violations %s', async (_name, options) => {
        // Arrange
        const wrapper = render(options);

        // Act
        const violations = await axeViolations(wrapper);

        // Assert
        expect(violations).toEqual([]);
    });
});
