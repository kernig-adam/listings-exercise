import axe from 'axe-core';

// jsdom has no canvas; axe probes it for icon fonts and jsdom logs a noisy "not implemented" error.
HTMLCanvasElement.prototype.getContext = () => null;

// WCAG 2.2 AA rule tags.
const tags = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

/**
 * Run axe on a mounted wrapper (mount with attachTo: document.body) and return violations.
 * color-contrast is off because jsdom can't compute styles; contrast is checked
 * against the theme tokens instead (see app.css).
 */
export async function axeViolations(wrapper) {
    const results = await axe.run(wrapper.element, {
        runOnly: { type: 'tag', values: tags },
        rules: { 'color-contrast': { enabled: false } },
    });

    return results.violations;
}
