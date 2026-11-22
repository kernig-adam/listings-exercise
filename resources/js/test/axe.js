import axe from 'axe-core';

// jsdom has no canvas; axe probes it for icon fonts and jsdom logs a noisy "not implemented" error.
HTMLCanvasElement.prototype.getContext = () => null;

// WCAG 2.2 AA rule tags.
const tags = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

/**
 * Run axe on the document (mount with attachTo: document.body) and return violations.
 * color-contrast is off because jsdom can't compute styles; contrast is checked
 * against the theme tokens instead (see app.css).
 */
export async function axeViolations() {
    // The whole document, not wrapper.element: a multi-root page component has no single root to scan.
    const results = await axe.run(document.body, {
        runOnly: { type: 'tag', values: tags },
        rules: { 'color-contrast': { enabled: false } },
    });

    return results.violations;
}
