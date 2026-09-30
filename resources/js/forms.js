/*
 * One tap, one submission.
 *
 * On a single bar of school wifi a button that appears to do nothing gets
 * tapped four times (spec section 9). The first tap marks the pressed button
 * as busy — and swaps in its data-busy-text, if it has one — and any further
 * submit of the same form is ignored until the next page arrives.
 *
 * Forms driven by htmx are left alone: htmx cancels the native submit itself
 * and shows its own indicators. Nothing here is required for a form to work.
 */

const RELEASE_AFTER_MS = 8000;

/*
 * The few behaviours that used to be inline onclick/onsubmit/onfocus
 * attributes. They live here so the Content-Security-Policy can refuse every
 * inline script: then even text that slipped past escaping could not run on
 * the director's screen.
 *
 *   data-confirm="…"        on a form: ask before submitting
 *   data-print              on a button: open the print dialog
 *   data-select-on-focus    on an input: select its contents to copy
 */

// Registered before the busy handler below, so a cancelled confirm never
// leaves a button looking busy.
document.addEventListener('submit', (event) => {
    const form = event.target;

    if (form instanceof HTMLFormElement && form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
        event.preventDefault();
    }
});

document.addEventListener('click', (event) => {
    if (event.target.closest('[data-print]')) {
        window.print();
    }
});

document.addEventListener('focusin', (event) => {
    if (event.target.matches?.('[data-select-on-focus]')) {
        event.target.select();
    }
});

function release(form) {
    delete form.dataset.submitting;

    form.querySelectorAll('[aria-busy="true"]').forEach((button) => {
        button.removeAttribute('aria-busy');

        if (button.dataset.idleText !== undefined) {
            button.textContent = button.dataset.idleText;
            delete button.dataset.idleText;
        }
    });
}

document.addEventListener('submit', (event) => {
    const form = event.target;

    // A confirm() that was cancelled, or htmx taking over the request.
    if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;

    if (form.dataset.submitting === 'true') {
        event.preventDefault();
        return;
    }

    form.dataset.submitting = 'true';

    const button = event.submitter;

    if (button) {
        button.setAttribute('aria-busy', 'true');

        if (button.dataset.busyText) {
            button.dataset.idleText = button.textContent;
            button.textContent = button.dataset.busyText;
        }
    }

    // A label sheet downloads rather than navigating, so the page stays where
    // it is. Never leave a button dead: let go again after a few seconds.
    window.setTimeout(() => release(form), RELEASE_AFTER_MS);
});

// Coming back with the browser's Back button restores the page from cache,
// busy buttons included.
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        document.querySelectorAll('form[data-submitting]').forEach(release);
    }
});
