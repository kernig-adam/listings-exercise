import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { axeViolations } from '../test/axe';
import IconTooltip from './IconTooltip.vue';

const render = () =>
    mount(IconTooltip, {
        props: { text: 'Saved searches' },
        slots: { default: '<a href="/saved-searches" aria-label="Saved searches">icon</a>' },
        attachTo: document.body,
    });

const tip = (wrapper) => wrapper.get('[aria-hidden="true"]');
const isShown = (wrapper) => tip(wrapper).element.style.display !== 'none';

describe('IconTooltip', () => {
    it('is hidden until the control is hovered or focused', async () => {
        // Arrange
        const wrapper = render();
        const hiddenAtFirst = !isShown(wrapper);

        // Act
        await wrapper.trigger('mouseenter');
        const onHover = isShown(wrapper);
        await wrapper.trigger('mouseleave');
        await wrapper.get('a').trigger('focusin');
        const onFocus = isShown(wrapper);

        // Assert
        expect(hiddenAtFirst).toBe(true);
        expect(onHover).toBe(true);
        expect(onFocus).toBe(true);
    });

    it('hides on Escape without moving focus', async () => {
        // Arrange
        const wrapper = render();
        const link = wrapper.get('a');
        link.element.focus();
        await link.trigger('focusin');

        // Act
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await wrapper.vm.$nextTick();

        // Assert
        expect(isShown(wrapper)).toBe(false);
        expect(document.activeElement).toBe(link.element);
    });

    it('stays visible while the pointer is on the tooltip itself', async () => {
        // Arrange
        const wrapper = render();
        await wrapper.trigger('mouseenter');

        // Act: the tooltip is inside the wrapper, so moving onto it fires no mouseleave on the wrapper.
        await tip(wrapper).trigger('mouseover');

        // Assert
        expect(isShown(wrapper)).toBe(true);
        expect(wrapper.element.contains(tip(wrapper).element)).toBe(true);
    });

    it('is not a title attribute and does not rename the control', () => {
        // Arrange / Act
        const wrapper = render();

        // Assert
        expect(wrapper.find('[title]').exists()).toBe(false);
        expect(wrapper.get('a').attributes('aria-label')).toBe('Saved searches');
        expect(tip(wrapper).attributes('aria-hidden')).toBe('true');
    });

    it('has no axe violations', async () => {
        // Arrange
        const wrapper = render();
        await wrapper.trigger('mouseenter');

        // Act
        const violations = await axeViolations();

        // Assert
        expect(violations).toEqual([]);
    });
});
