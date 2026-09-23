import htmx from 'htmx.org';

// htmx is the only JavaScript in this application. No SPA, no bundled framework.
window.htmx = htmx;

// Disable htmx's own indicator stylesheet injection; ours lives in app.css so it
// ships in the compiled bundle rather than being written into every page head.
htmx.config.includeIndicatorStyles = false;

// Keyboard behaviour for the admin search box. No-ops on pages without one.
import './search';

// Busy state on the button that was pressed, and no double submissions.
import './forms';
