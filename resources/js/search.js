/*
 * Keyboard behaviour for the header search box.
 *
 * Results are real links, so this file never reimplements "open the result" —
 * it moves DOM focus onto an anchor and lets Enter do what Enter does. That
 * keeps the whole thing working for screen readers and for anyone who prefers
 * Tab to arrow keys.
 *
 * Everything degrades: with this file absent the box is still a form that
 * submits to a full results page.
 */

const SELECTOR = {
    root: '[data-search]',
    input: '[data-search-input]',
    panel: '[data-search-panel]',
    result: '[data-search-result]',
};

function typingSomewhereElse(target) {
    if (!target) return false;

    return (
        target.isContentEditable ||
        ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)
    );
}

function setUp(root) {
    const input = root.querySelector(SELECTOR.input);
    const panel = root.querySelector(SELECTOR.panel);

    if (!input || !panel) return;

    const results = () => Array.from(panel.querySelectorAll(SELECTOR.result));

    const open = () => {
        if (panel.hidden && panel.children.length > 0) {
            panel.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }
    };

    const close = () => {
        panel.hidden = true;
        input.setAttribute('aria-expanded', 'false');
    };

    // Show the panel once htmx has put something in it, and hide it again when
    // the box is emptied.
    panel.addEventListener('htmx:afterSwap', () => {
        input.value.trim() === '' && panel.children.length === 0 ? close() : open();
    });

    // "/" from anywhere on the page, as long as the person is not already
    // typing into something.
    document.addEventListener('keydown', (event) => {
        if (event.key !== '/' || event.metaKey || event.ctrlKey || event.altKey) return;
        if (typingSomewhereElse(event.target)) return;

        event.preventDefault();
        input.focus();
        input.select();
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown') {
            const first = results()[0];

            if (first) {
                event.preventDefault();
                open();
                first.focus();
            }
        }

        if (event.key === 'Escape') {
            close();
            input.blur();
        }
    });

    // Arrow keys move between results; Escape and ArrowUp past the top go back
    // to the box. Enter is the anchor's own.
    panel.addEventListener('keydown', (event) => {
        const items = results();
        const index = items.indexOf(document.activeElement);

        if (index === -1) return;

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            items[Math.min(index + 1, items.length - 1)].focus();
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            index === 0 ? input.focus() : items[index - 1].focus();
        }

        if (event.key === 'Escape') {
            close();
            input.focus();
        }
    });

    // Clicking away closes it, but moving focus between the box and a result
    // must not.
    document.addEventListener('focusin', (event) => {
        if (!root.contains(event.target)) close();
    });

    document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) close();
    });
}

document.querySelectorAll(SELECTOR.root).forEach(setUp);
