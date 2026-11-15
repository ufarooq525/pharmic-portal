/*
 * Small progressive enhancements on server-rendered Blade: theme toggle, auto-submitting filters,
 * password eye, one-click submit buttons, confirm prompts and the AJAX file upload.
 * Every page still works without JavaScript.
 */

const THEME_KEY = 'pharmic-theme';

function applyTheme(theme) {
    document.documentElement.dataset.theme = theme;
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.setAttribute('aria-pressed', String(theme === 'dark'));
        const label = button.querySelector('[data-theme-label]');
        if (label) label.textContent = theme === 'dark' ? 'Light mode' : 'Dark mode';
    });
}

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-theme-toggle]');
    if (!toggle) return;

    const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    try { localStorage.setItem(THEME_KEY, next); } catch (e) { /* private mode: theme still switches for this page */ }
    applyTheme(next);
});

// Follow the OS setting until the person picks a theme themselves.
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (event) => {
    let saved = null;
    try { saved = localStorage.getItem(THEME_KEY); } catch (e) { /* ignore */ }
    if (!saved) applyTheme(event.matches ? 'dark' : 'light');
});

document.addEventListener('change', (event) => {
    if (event.target.matches('[data-autosubmit]')) event.target.form?.requestSubmit();
});

applyTheme(document.documentElement.dataset.theme || 'light');

/* Password eye */
document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-password-toggle]');
    if (!button) return;

    // The input beside the button (drawer copies rename ids, so don't look it up by id).
    const input = button.parentElement.querySelector('input');
    if (!input) return;
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    // The name stays "Show password"; aria-pressed alone says whether it is on.
    button.setAttribute('aria-pressed', String(show));
    button.querySelector('[data-eye="show"]')?.classList.toggle('hidden', show);
    button.querySelector('[data-eye="hide"]')?.classList.toggle('hidden', !show);
    input.focus();
});

/* Submit buttons lock after the first click, so a slow page can't post twice.
 * They use aria-disabled, not disabled: a disabled button drops keyboard focus to <body>,
 * and screen reader users lose their place and never hear the busy label. */
function lockButtons(form, busy) {
    form.querySelectorAll('button:not([type="button"]), input[type="submit"]').forEach((button) => {
        if (busy) {
            button.dataset.idleLabel ??= button.textContent;
            const label = button.dataset.busyLabel || button.textContent.trim();
            button.replaceChildren(Object.assign(document.createElement('span'), { className: 'spinner', ariaHidden: 'true' }), document.createTextNode(' ' + label));
        } else if (button.dataset.idleLabel !== undefined) {
            button.textContent = button.dataset.idleLabel;
        }
        if (busy) button.setAttribute('aria-disabled', 'true');
        else button.removeAttribute('aria-disabled');
    });
    form.toggleAttribute('aria-busy', busy);
}

/*
 * Confirm popup for destructive forms (data-confirm="Discard this file? Nothing has been posted.").
 * The first sentence becomes the title, the rest the explanation, and the form's own button label
 * ("Revert and remove", "Cancel invoice") becomes the confirm button, so people see exactly what happens.
 * Focus starts on the safe choice. Falls back to the browser's confirm() where <dialog> is missing.
 */
const confirmDialog = document.getElementById('confirm-dialog');

function askConfirm({ title, body = '', ok = 'Confirm', cancel = 'Keep it' }) {
    if (!confirmDialog?.showModal) return Promise.resolve(window.confirm([title, body].filter(Boolean).join('\n\n')));

    confirmDialog.querySelector('[data-confirm-title]').textContent = title;
    const bodyEl = confirmDialog.querySelector('[data-confirm-body]');
    bodyEl.textContent = body;
    bodyEl.hidden = !body;
    const okButton = confirmDialog.querySelector('[data-confirm-ok]');
    const cancelButton = confirmDialog.querySelector('[data-confirm-cancel]');
    okButton.textContent = ok;
    cancelButton.textContent = cancel;

    return new Promise((resolve) => {
        const finish = (answer) => {
            okButton.removeEventListener('click', onOk);
            cancelButton.removeEventListener('click', onCancel);
            confirmDialog.removeEventListener('close', onClose);
            if (confirmDialog.open) confirmDialog.close();
            resolve(answer);
        };
        const onOk = () => finish(true);
        const onCancel = () => finish(false);
        const onClose = () => finish(false); // Escape
        okButton.addEventListener('click', onOk);
        cancelButton.addEventListener('click', onCancel);
        confirmDialog.addEventListener('close', onClose);
        confirmDialog.showModal();
        cancelButton.focus();
    });
}

function splitQuestion(text) {
    const at = text.search(/[?.!](\s|$)/);
    return at === -1 ? { title: text.trim(), body: '' } : { title: text.slice(0, at + 1).trim(), body: text.slice(at + 1).trim() };
}

// Capture phase, so these run before the upload, drawer and background-save handlers on the form itself.
document.addEventListener('submit', (event) => {
    const form = event.target;

    // A busy form ignores further submits (button clicks, Enter in a field, requestSubmit).
    if (form.hasAttribute('aria-busy')) {
        event.preventDefault();
        event.stopImmediatePropagation();
        return;
    }

    if (!form.dataset.confirm) return;
    if (form.dataset.confirmed) { delete form.dataset.confirmed; return; }

    event.preventDefault();
    event.stopImmediatePropagation();
    const submitter = event.submitter;
    const label = submitter?.textContent.trim() || 'Confirm';
    askConfirm({ ...splitQuestion(form.dataset.confirm), ok: label }).then((yes) => {
        if (!yes) return;
        form.dataset.confirmed = '1';
        submitter && submitter.form === form ? form.requestSubmit(submitter) : form.requestSubmit();
    });
}, true);

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (event.defaultPrevented || form.hasAttribute('data-upload')) return;
    if (form.hasAttribute('data-ajax')) {
        event.preventDefault();
        backgroundSubmit(form);
        return;
    }
    showLoader();
    if (form.method.toLowerCase() === 'get') return;

    // After the browser has collected the form data, so the clicked button's value still posts.
    setTimeout(() => lockButtons(form, true), 0);
});

// Coming back with the browser's Back button shows the cached page: unlock it.
window.addEventListener('pageshow', (event) => {
    hideLoader();
    if (event.persisted) document.querySelectorAll('form[aria-busy]').forEach((form) => lockButtons(form, false));
});

/* Page loader: a bar across the top from the moment a link is clicked until the next page shows. */
// The page actually shown, with its filters (the address bar only shows the path; see showSoftPage).
let currentFetch = location.origin + location.pathname + location.search;
const cleanAddress = (url) => url.pathname + url.hash;
const loader = document.getElementById('page-loader');
const fetchPill = document.getElementById('fetch-pill');
let loaderTimer;
function showLoader() {
    if (!loader) return;
    clearTimeout(loaderTimer);
    // Fast pages never flash the bar; it appears only if loading takes over 120 ms.
    loaderTimer = setTimeout(() => {
        loader.dataset.active = '';
        document.body.setAttribute('aria-busy', 'true');
        if (fetchPill) fetchPill.hidden = false;
        document.querySelectorAll('[data-refresh]').forEach((b) => b.setAttribute('aria-busy', 'true'));
    }, 120);
}
function hideLoader() {
    clearTimeout(loaderTimer);
    if (loader) delete loader.dataset.active;
    document.body.removeAttribute('aria-busy');
    if (fetchPill) fetchPill.hidden = true;
    document.querySelectorAll('[data-refresh][aria-busy]').forEach((b) => b.removeAttribute('aria-busy'));
}

const isPageLink = (a, event) => a && a.href && !a.target && !a.hasAttribute('download')
    && a.origin === location.origin && !(a.pathname === location.pathname && a.hash)
    && !(event && (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0))
    && !/\/(file|export|print|payslip|template)(\?|$|\/)/.test(a.pathname);

document.addEventListener('click', (event) => {
    const a = event.target.closest('a');
    if (!event.defaultPrevented && isPageLink(a, event)) showLoader();
});

/* Speed: pages are fetched while the pointer rests on their link by the speculation rules in the layout's
 * <head>. (A <link rel=prefetch> did the same, but pages opened from it lost the cross-page fade and
 * logged "Transition was aborted" errors.) */

/* AJAX upload: drag and drop, upload progress, then the four reading steps from the prototype. */
const formatSize = (bytes) => (bytes > 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB');
const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

function setupUpload(form) {
    const input = form.querySelector('input[type="file"]');
    const zone = form.querySelector('[data-dropzone]');
    const nameTag = form.querySelector('[data-file-name]');
    const panel = form.querySelector('[data-upload-progress]');
    const errorBox = form.querySelector('[data-upload-errors]');
    const steps = [...form.querySelectorAll('[data-step]')];
    const q = (selector) => panel.querySelector(selector);

    const showFile = () => {
        const file = input.files[0];
        nameTag.classList.toggle('hidden', !file);
        if (file) nameTag.textContent = `${file.name} · ${formatSize(file.size)}`;
    };

    const markStep = (index, state, result = '') => {
        const step = steps[index];
        const dot = step.querySelector('[data-step-dot]');
        step.classList.toggle('opacity-55', state === 'idle');
        step.querySelector('[data-step-result]').textContent = result;
        dot.className = 'flex size-7 flex-none items-center justify-center rounded-full text-[13px] font-extrabold '
            + ({ idle: 'bg-seg text-nav', now: 'bg-info-soft text-info', ok: 'bg-accent text-white', bad: 'bg-bad text-white' })[state];
        dot.textContent = state === 'ok' ? '✓' : state === 'bad' ? '✕' : String(index + 1);
        if (state === 'now') dot.replaceChildren(Object.assign(document.createElement('span'), { className: 'spinner size-3.5', ariaHidden: 'true' }));
    };

    const showErrors = (messages) => {
        errorBox.innerHTML = '';
        messages.forEach((message) => errorBox.appendChild(Object.assign(document.createElement('p'), { textContent: message, className: 'm-0' })));
        errorBox.classList.remove('hidden');
        panel.classList.add('hidden');
        zone.classList.remove('hidden');
        lockButtons(form, false);
    };

    zone.addEventListener('dragover', (event) => { event.preventDefault(); zone.dataset.over = ''; });
    zone.addEventListener('dragleave', () => delete zone.dataset.over);
    zone.addEventListener('drop', (event) => {
        event.preventDefault();
        delete zone.dataset.over;
        if (!event.dataTransfer.files.length) return;
        input.files = event.dataTransfer.files;
        showFile();
        form.requestSubmit();
    });
    input.addEventListener('change', () => { showFile(); if (input.files.length) form.requestSubmit(); });

    form.addEventListener('submit', (event) => {
        if (event.defaultPrevented) return;
        event.preventDefault();

        const file = input.files[0];
        if (!file) { input.click(); return; }
        if (!/\.pdf$/i.test(file.name)) { showErrors(['Choose the PDF file the distributor sent.']); return; }

        errorBox.classList.add('hidden');
        zone.classList.add('hidden');
        panel.classList.remove('hidden');
        q('[data-progress-file]').textContent = file.name;
        steps.forEach((_, i) => markStep(i, 'idle'));
        lockButtons(form, true);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', form.action);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.upload.addEventListener('progress', (e) => {
            if (!e.lengthComputable) return;
            const pct = Math.round((e.loaded / e.total) * 100);
            q('[data-progress-bar]').style.width = pct + '%';
            q('[data-progress-pct]').textContent = pct + '%';
        });
        xhr.upload.addEventListener('load', () => {
            q('[data-progress-label]').textContent = 'Uploaded · reading the file';
            q('[data-progress-pct]').textContent = '100%';
            q('[data-progress-bar]').style.width = '100%';
            markStep(0, 'now', 'Working…');
        });

        xhr.addEventListener('load', async () => {
            let body = {};
            try { body = JSON.parse(xhr.responseText); } catch (e) { /* not JSON */ }

            if (xhr.status === 419) return showErrors(['Your session expired. Reload the page and try again.']);
            if (xhr.status >= 400 || !body.redirect) {
                const messages = body.errors ? Object.values(body.errors).flat() : [body.message || 'The file could not be read. Try again, or send it to support.'];
                return showErrors(messages);
            }

            for (const [i, step] of body.steps.entries()) {
                markStep(i, 'now', 'Working…');
                await wait(380);
                markStep(i, step.ok ? 'ok' : 'bad', step.result);
            }
            q('[data-progress-label]').textContent = 'Opening the review…';
            showLoader();
            await wait(400);
            softNavigate(new URL(body.redirect, location.href));
        });
        xhr.addEventListener('error', () => showErrors(['The upload stopped. Check the connection and try again.']));

        xhr.send(new FormData(form));
    });
}

document.querySelectorAll('form[data-upload]').forEach(setupUpload);

/* Sidebar on phones and tablets */
const sidebar = document.getElementById('sidebar');
function setSidebar(open) {
    if (!sidebar) return;
    sidebar.toggleAttribute('data-open', open);
    document.querySelector('[data-sidebar-open]')?.setAttribute('aria-expanded', String(open));
}
document.addEventListener('click', (event) => {
    if (event.target.closest('[data-sidebar-open]')) setSidebar(true);
    else if (event.target.closest('[data-sidebar-close]')) setSidebar(false);
});

/* Collapse the sidebar to icons on wide screens; remembered on this device. */
function syncCollapseButton() {
    const collapsed = document.documentElement.classList.contains('sb-collapsed');
    const button = document.querySelector('[data-sidebar-collapse]');
    if (!button) return;
    button.setAttribute('aria-pressed', String(collapsed));
    button.title = collapsed ? 'Expand menu' : 'Collapse menu';
    button.setAttribute('aria-label', button.title);
}
document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-sidebar-collapse]');
    const toPeriod = event.target.closest('[data-expand-to-period]');
    if (!toggle && !toPeriod) return;
    event.preventDefault();
    const collapsed = toPeriod ? false : !document.documentElement.classList.contains('sb-collapsed');
    document.documentElement.classList.toggle('sb-collapsed', collapsed);
    try { localStorage.setItem('pharmic-sidebar', collapsed ? 'collapsed' : 'open'); } catch (e) {}
    syncCollapseButton();
    if (toPeriod) setTimeout(() => document.getElementById('period')?.showPicker?.(), 220);
});
syncCollapseButton();

/*
 * Toasts: stack at the bottom, slide in, and leave on their own (errors stay longer).
 * Hovering or focusing one pauses its timer; the × closes it. The stack is a polite live region,
 * and an error toast is role="alert" so screen readers announce it straight away.
 */
const TOAST_ICONS = {
    ok: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>',
    bad: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5v.01"/></svg>',
};

function toast(message, tone = 'ok') {
    const stack = document.getElementById('toasts');
    if (!stack || !message) return;

    const item = document.createElement('div');
    item.className = 'toast';
    item.dataset.tone = tone;
    if (tone === 'bad') item.setAttribute('role', 'alert');
    item.innerHTML = `<span class="toast-icon">${TOAST_ICONS[tone] || TOAST_ICONS.ok}</span><span class="min-w-0 flex-1"></span>`
        + '<button type="button" class="toast-close" aria-label="Dismiss"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button>';
    item.children[1].textContent = message;

    const leave = () => {
        clearTimeout(item.timer);
        delete item.dataset.show;
        setTimeout(() => item.remove(), 220);
    };
    const start = () => { clearTimeout(item.timer); item.timer = setTimeout(leave, tone === 'bad' ? 8000 : 4500); };
    item.querySelector('.toast-close').addEventListener('click', leave);
    item.addEventListener('mouseenter', () => clearTimeout(item.timer));
    item.addEventListener('mouseleave', start);
    item.addEventListener('focusin', () => clearTimeout(item.timer));
    item.addEventListener('focusout', start);

    stack.appendChild(item);
    while (stack.children.length > 3) stack.firstElementChild.remove();
    requestAnimationFrame(() => requestAnimationFrame(() => { item.dataset.show = ''; }));
    start();
}

/*
 * Drawer: add and edit forms open from the right without leaving the page.
 * A link with data-drawer="#section-id" shows that section: from this page if the link is "#id",
 * otherwise fetched from the link's URL (e.g. ?edit=5). The form posts in the background:
 * validation errors show inside the drawer; on success the page content refreshes in place.
 */
const drawer = document.getElementById('drawer');
const drawerBody = document.getElementById('drawer-body');
const drawerTitle = document.getElementById('drawer-title');
let drawerReturnFocus = null;
let drawerDirty = false;
const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// While the drawer is open the page behind it is inert: Tab stays in the drawer, clicks can't reach the page.
function setPageInert(inert) {
    [...document.body.children].forEach((el) => {
        if (el !== drawer && el.id !== 'toasts' && el !== confirmDialog && el.tagName !== 'SCRIPT') el.inert = inert;
    });
}

function showDrawer(title) {
    drawerTitle.textContent = title || '';
    drawerDirty = false;
    if (!drawer.hidden && drawer.hasAttribute('data-open')) return;
    clearTimeout(drawer.closeTimer);
    drawer.hidden = false;
    setPageInert(true);
    document.body.style.overflow = 'hidden';
    requestAnimationFrame(() => requestAnimationFrame(() => { drawer.dataset.open = ''; }));
}

// Placeholder rows while the form is fetched, so the drawer opens at once instead of after the request.
function drawerLoading() {
    const skeleton = document.getElementById('drawer-skeleton');
    drawerBody.replaceChildren(skeleton ? skeleton.content.cloneNode(true) : document.createTextNode('Loading…'));
    drawerBody.setAttribute('aria-busy', 'true');
    drawer.querySelector('[data-drawer-close]:not(.drawer-backdrop)')?.focus();
}

function drawerFailed(link, selector) {
    drawerBody.removeAttribute('aria-busy');
    const box = Object.assign(document.createElement('div'), { className: 'flex flex-col items-start gap-3 rounded-[14px] bg-bad-soft px-4 py-4 text-bad' });
    box.setAttribute('role', 'alert');
    box.append(
        Object.assign(document.createElement('p'), { className: 'm-0 font-bold', textContent: 'This form could not be loaded.' }),
        Object.assign(document.createElement('p'), { className: 'm-0 text-[13px]', textContent: 'Check the connection and try again, or open it as a full page.' }),
    );
    const actions = Object.assign(document.createElement('div'), { className: 'flex flex-wrap gap-2' });
    const retry = Object.assign(document.createElement('button'), { type: 'button', className: 'btn btn-sm', textContent: 'Try again' });
    retry.addEventListener('click', () => loadDrawer(link, selector));
    actions.append(retry, Object.assign(document.createElement('a'), { href: link.href, className: 'btn btn-sm', textContent: 'Open full page' }));
    box.append(actions);
    drawerBody.replaceChildren(box);
    retry.focus();
}

async function loadDrawer(link, selector) {
    drawerLoading();
    try {
        const { response, doc } = await fetchPage(link.href);
        if (!response.ok) throw new Error(String(response.status));
        const section = doc.querySelector(selector);
        section ? openDrawer(section, link.dataset.drawerTitle) : location.assign(link.href);
    } catch (e) {
        drawerFailed(link, selector);
    }
}

function openDrawer(section, title) {
    const copy = section.cloneNode(true);
    copy.removeAttribute('id');
    copy.removeAttribute('data-drawer-source');
    // The hidden original stays on the page, so give the copy's ids a prefix to keep labels working.
    copy.querySelectorAll('[id]').forEach((el) => { el.id = 'dw-' + el.id; });
    copy.querySelectorAll('label[for]').forEach((el) => { el.htmlFor = 'dw-' + el.htmlFor; });
    copy.querySelectorAll('[list]').forEach((el) => el.setAttribute('list', 'dw-' + el.getAttribute('list')));
    const heading = copy.querySelector('h2, .card-title');
    showDrawer(title || heading?.textContent.trim() || '');
    heading?.remove();
    drawerBody.removeAttribute('aria-busy');
    drawerBody.replaceChildren(...copy.childNodes);
    drawerBody.classList.remove('drawer-enter');
    void drawerBody.offsetWidth; // restart the fade-in when content replaces the skeleton
    drawerBody.classList.add('drawer-enter');
    drawerBody.querySelectorAll('[data-permission-form]').forEach(refreshPermissions);
    drawerBody.querySelector('input:not([type=hidden]), select, textarea')?.focus({ preventScroll: true });
}

// Typing in the drawer marks it as changed, so closing it by accident asks first.
drawerBody?.addEventListener('input', () => { drawerDirty = true; });

async function closeDrawer({ force = false } = {}) {
    if (!drawer || drawer.hidden) return;
    if (drawerDirty && !force) {
        const discard = await askConfirm({ title: 'Discard your changes?', body: 'What you typed in this form has not been saved.', ok: 'Discard changes', cancel: 'Keep editing' });
        if (!discard) return;
    }
    drawerDirty = false;
    delete drawer.dataset.open;
    document.body.style.overflow = '';
    setPageInert(false);
    drawer.closeTimer = setTimeout(() => { drawer.hidden = true; drawerBody.replaceChildren(); }, reducedMotion() ? 0 : 260);

    // The link that opened the drawer may have been replaced by a refresh; fall back to the page heading.
    let target = drawerReturnFocus?.isConnected ? drawerReturnFocus : document.querySelector('#main h1');
    if (target && !drawerReturnFocus?.isConnected) target.setAttribute('tabindex', '-1');
    target?.focus({ preventScroll: true });
}

async function fetchPage(url, options = {}) {
    // The address bar shows only the page; the real address (with its filters) goes as the referrer,
    // so a save that redirects back() returns to the same filtered view.
    const response = await fetch(url, { credentials: 'same-origin', referrer: currentFetch, ...options, headers: { 'X-Requested-With': 'XMLHttpRequest', ...(options.headers || {}) } });
    return { response, doc: new DOMParser().parseFromString(await response.text(), 'text/html') };
}

// Swap in the main content (and sidebar badges) from a freshly loaded page, without a full reload.
// Where the browser supports view transitions the old and new content cross-fade instead of jumping.
function swapPage(doc, url, afterApply, animate = true) {
    const main = doc.getElementById('main');
    if (!main) { location.assign(url); return; }

    const status = main.querySelector('[role=status]');
    const message = status?.textContent.trim();
    status?.remove();

    const apply = () => {
        document.getElementById('main').replaceWith(main);
        const nav = doc.querySelector('#sidebar nav');
        if (nav) document.querySelector('#sidebar nav')?.replaceWith(nav);
        // The month picker and month labels outside the content (sidebar, phone header).
        doc.querySelectorAll('[data-swap]').forEach((el) => document.querySelector(`[data-swap="${el.dataset.swap}"]`)?.replaceWith(el));
        document.title = doc.title;
        main.querySelectorAll('form[data-upload]').forEach(setupUpload);
        afterApply?.(main);
        // The focused button or link may have been replaced; keep keyboard users on the page.
        if (!document.activeElement || document.activeElement === document.body || !document.activeElement.isConnected) {
            const heading = main.querySelector('h1');
            heading?.setAttribute('tabindex', '-1');
            heading?.focus({ preventScroll: true });
        }
    };

    const done = () => {
        if (message) toast(message);
        else main.querySelector('[role=alert]')?.scrollIntoView({ block: 'center', behavior: reducedMotion() ? 'auto' : 'smooth' });
    };

    // Not while the drawer slides out: a view transition would freeze it mid-slide.
    if (animate && document.startViewTransition && !reducedMotion() && drawer?.hidden !== false) {
        const transition = document.startViewTransition(apply);
        // Leaving the page mid-fade aborts the transition; that is fine, so don't report it as an error.
        transition.ready.catch(() => {});
        transition.updateCallbackDone.catch(() => {});
        transition.finished.then(done, done);
    }
    else { apply(); done(); }
}

document.addEventListener('click', async (event) => {
    if (event.target.closest('[data-drawer-close]')) { closeDrawer(); return; }

    const link = event.target.closest('[data-drawer]');
    if (!link || event.metaKey || event.ctrlKey) return;
    event.preventDefault();
    drawerReturnFocus = link;
    const selector = link.dataset.drawer;
    const href = link.getAttribute('href') || '';

    if (href.startsWith('#')) {
        const section = document.querySelector(selector);
        if (section) openDrawer(section, link.dataset.drawerTitle);
        return;
    }

    // Open straight away with placeholders, then fill in the form when it arrives.
    showDrawer(link.dataset.drawerTitle || link.textContent.trim().replace(/\s+/g, ' '));
    loadDrawer(link, selector);
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape' || confirmDialog?.open) return; // the popup handles its own Escape
    if (drawer && !drawer.hidden) {
        // Without this, the same key press would also close the "Discard your changes?" popup it opens.
        event.preventDefault();
        closeDrawer();
    } else setSidebar(false);
});

// Forms inside the drawer, and forms marked data-ajax, post in the background.
drawer?.addEventListener('submit', (event) => {
    if (event.defaultPrevented) return;
    event.preventDefault();
    backgroundSubmit(event.target, true);
});

async function backgroundSubmit(form, inDrawer = false) {
    form.querySelectorAll('.field-error, [data-form-problem]').forEach((el) => el.remove());
    form.querySelectorAll('[aria-invalid]').forEach((el) => el.removeAttribute('aria-invalid'));
    lockButtons(form, true);
    if (!inDrawer) showLoader();

    const fail = (message) => {
        lockButtons(form, false);
        hideLoader();
        if (inDrawer) {
            // dataset can't be set with Object.assign (it is read-only), so mark the box separately.
            const box = Object.assign(document.createElement('div'), { className: 'rounded-[14px] bg-bad-soft px-3.5 py-3 font-semibold text-bad', textContent: message });
            box.dataset.formProblem = '';
            box.setAttribute('role', 'alert');
            form.prepend(box);
        } else toast(message, 'bad');
    };

    try {
        const { response, doc } = await fetchPage(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json, text/html' } });

        if (response.status === 422 && response.headers.get('content-type')?.includes('json')) {
            const body = JSON.parse(doc.body.textContent || '{}');
            let placed = false;
            Object.entries(body.errors || {}).forEach(([name, messages]) => {
                const key = name.replace(/\.(\w+)/g, '[$1]');
                const input = form.querySelector(`[name="${key}"], [name="${key}[]"]`);
                if (inDrawer && input) {
                    input.setAttribute('aria-invalid', 'true');
                    (input.closest('div, fieldset') || input).appendChild(Object.assign(document.createElement('p'), { className: 'field-error', textContent: messages[0] }));
                    placed = true;
                }
            });
            if (!placed) return fail(Object.values(body.errors || {}).flat()[0] || body.message || 'Please check the form.');
            lockButtons(form, false);
            form.querySelector('[aria-invalid]')?.focus();
            return;
        }
        if (!response.ok) return fail(response.status === 419 ? 'Your session expired. Reload the page and try again.' : 'Something went wrong (' + response.status + '). Nothing was saved.');

        // A business-rule error comes back as the page with an error box and no success message.
        const problem = doc.querySelector('#main [role=alert]');
        if (problem && !doc.querySelector('#main [role=status]')) return fail(problem.textContent.trim().replace(/\s+/g, ' '));

        if (inDrawer) closeDrawer({ force: true });
        hideLoader();
        // A save that lands on another page (discard a file → the imports list) is a new history entry.
        // Staying on the same page (issue an invoice, link a name): update in place, no fade.
        const next = new URL(response.url);
        const samePage = next.pathname === location.pathname;
        showSoftPage(doc, next, !samePage, samePage);
    } catch (e) {
        fail('Could not save. Check the connection and try again.');
    }
}

/*
 * Roles and people: the permission checklist stays consistent while you tick.
 * - Ticking an action ticks the "view" it needs; unticking a view unticks its actions.
 * - On a person, the role's permissions show ticked and locked; changing the role re-ticks at once,
 *   and the boxes the person had ticked themself (their extras) are remembered.
 * - Choosing "Medical rep" hides the role and permissions, which don't apply to them.
 */
function refreshPermissions(form) {
    const box = form.querySelector('[data-permissions]');
    form.querySelectorAll('[data-for-type]').forEach((section) => {
        section.hidden = section.dataset.forType !== (form.querySelector('[data-login-type]:checked')?.value || 'staff');
    });
    if (!box) return;

    const boxes = [...box.querySelectorAll('[data-perm]')];
    let inheritedCount = 0;
    if (box.dataset.mode === 'person') {
        const roleId = form.querySelector('[data-role-select]')?.value || '';
        const isOwner = JSON.parse(form.dataset.ownerRoles || '[]').map(String).includes(roleId);
        const fromRole = new Set(JSON.parse(form.dataset.rolePermissions || '{}')[roleId] || []);
        boxes.forEach((cb) => {
            const inherited = isOwner || fromRole.has(cb.value);
            cb.checked = inherited || cb.dataset.extra === '1';
            cb.disabled = inherited || cb.hasAttribute('data-locked');
            cb.closest('.perm').querySelector('[data-from-role]').hidden = !inherited;
            if (inherited) inheritedCount++;
        });
        box.querySelector('[data-owner-note]').hidden = !isOwner;
        const extrasNote = box.querySelector('[data-extras-note]');
        if (extrasNote) extrasNote.hidden = isOwner;
        const limits = form.querySelector('[data-hide-for-owner]');
        if (limits) limits.hidden = isOwner;
    }
    // An action someone could tick before the role changed still needs its view.
    boxes.filter((cb) => cb.checked && cb.dataset.requires).forEach((cb) => tickPermission(box, cb.dataset.requires, true));

    box.querySelectorAll('[data-perm-group]').forEach((group) => {
        const all = [...group.querySelectorAll('[data-perm]')];
        const on = all.filter((cb) => cb.checked).length;
        const count = group.querySelector('[data-perm-count]');
        count.textContent = `${on} of ${all.length}`;
        count.toggleAttribute('data-some', on > 0);
    });

    const ticked = boxes.filter((cb) => cb.checked).length;
    const summary = box.querySelector('[data-perm-summary]');
    if (summary) {
        summary.textContent = box.dataset.mode === 'person'
            ? `Can do ${ticked} of ${boxes.length}: ${inheritedCount} from the role${ticked > inheritedCount ? `, ${ticked - inheritedCount} extra` : ''}.`
            : `${ticked} of ${boxes.length} ticked.`;
    }
}

function tickPermission(box, value, on) {
    const cb = box.querySelector(`[data-perm="${value}"]`);
    if (!cb || cb.disabled || cb.checked === on) return;
    cb.checked = on;
    cb.dataset.extra = on ? '1' : '0';
}

document.addEventListener('change', (event) => {
    const form = event.target.closest('[data-permission-form]');
    if (!form) return;
    const cb = event.target.closest('[data-perm]');
    if (cb) {
        const box = cb.closest('[data-permissions]');
        cb.dataset.extra = cb.checked ? '1' : '0';
        if (cb.checked && cb.dataset.requires) tickPermission(box, cb.dataset.requires, true);
        if (!cb.checked) box.querySelectorAll(`[data-requires="${cb.value}"]`).forEach((a) => tickPermission(box, a.value, false));
    }
    refreshPermissions(form);
});

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-select-group]');
    if (!button) return;
    const group = button.closest('[data-perm-group]');
    const boxes = [...group.querySelectorAll('[data-perm]:not(:disabled)')];
    const on = boxes.some((cb) => !cb.checked);
    boxes.forEach((cb) => { cb.checked = on; cb.dataset.extra = on ? '1' : '0'; });
    drawerDirty = true;
    refreshPermissions(button.closest('[data-permission-form]'));
});

document.querySelectorAll('[data-permission-form]').forEach(refreshPermissions);

/*
 * Permission groups collapse (closed by default; each header shows "2 of 4" ticked) and the list can
 * be searched: matching permissions show with their group opened, the rest are hidden while searching.
 */
function showPermGroups(box) {
    const words = (box.querySelector('[data-perm-search]')?.value || '').toLowerCase().split(/\s+/).filter(Boolean);
    let matches = 0;
    box.querySelectorAll('[data-perm-group]').forEach((group) => {
        const name = group.querySelector('.perm-group-name').textContent.toLowerCase();
        let visible = 0;
        group.querySelectorAll('.perm').forEach((perm) => {
            const text = name + ' ' + perm.textContent.toLowerCase();
            perm.hidden = !words.every((w) => text.includes(w));
            if (!perm.hidden) visible++;
        });
        matches += visible;
        const open = words.length ? visible > 0 : group.dataset.open === '1';
        group.hidden = words.length > 0 && visible === 0;
        group.querySelector('[data-perm-group-body]').hidden = !open;
        group.querySelector('[data-perm-group-toggle]').setAttribute('aria-expanded', String(open));
    });
    const empty = box.querySelector('[data-perm-empty]');
    empty.hidden = !words.length || matches > 0;
    empty.textContent = empty.hidden ? '' : `No permission matches “${words.join(' ')}”.`;
    const toggleAll = box.querySelector('[data-perm-toggle-all]');
    toggleAll.hidden = words.length > 0;
    toggleAll.textContent = [...box.querySelectorAll('[data-perm-group]')].every((g) => g.dataset.open === '1') ? 'Collapse all' : 'Expand all';
}

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-perm-group-toggle]');
    const toggleAll = event.target.closest('[data-perm-toggle-all]');
    if (!toggle && !toggleAll) return;
    const box = event.target.closest('[data-permissions]');
    if (toggle) {
        const group = toggle.closest('[data-perm-group]');
        const open = toggle.getAttribute('aria-expanded') !== 'true';
        // Opening or closing one group while searching ends the search, so what you see matches the header.
        const search = box.querySelector('[data-perm-search]');
        if (search.value) { search.value = ''; box.querySelectorAll('[data-perm-group]').forEach((g) => { g.dataset.open = g.querySelector('[data-perm-group-toggle]').getAttribute('aria-expanded') === 'true' ? '1' : g.dataset.open; }); }
        group.dataset.open = open ? '1' : '0';
    } else {
        const open = toggleAll.textContent.trim() === 'Expand all';
        box.querySelectorAll('[data-perm-group]').forEach((g) => { g.dataset.open = open ? '1' : '0'; });
    }
    showPermGroups(box);
});

document.addEventListener('input', (event) => {
    if (event.target.matches('[data-perm-search]')) showPermGroups(event.target.closest('[data-permissions]'));
});

// Enter in the search box must not save the form; Escape clears the search before it would close the drawer.
document.addEventListener('keydown', (event) => {
    const search = event.target.closest?.('[data-perm-search]');
    if (!search) return;
    if (event.key === 'Enter') event.preventDefault();
    if (event.key === 'Escape' && search.value) {
        event.preventDefault();
        event.stopPropagation();
        search.value = '';
        showPermGroups(search.closest('[data-permissions]'));
    }
}, true);

/*
 * Clickable table rows: a row that has a main link (marked data-row-target: Edit, Open, Payslip…)
 * opens it when you click anywhere on the row. Buttons, links and form fields in the row keep
 * working as before, selecting text does not trigger it, and Ctrl/⌘-click opens a new tab.
 * Keyboard users reach the same link with Tab, so nothing depends on the row click.
 */
document.addEventListener('click', (event) => {
    const row = event.target.closest('table.data tbody tr');
    if (!row || event.button !== 0 || event.target.closest('a, button, input, select, textarea, label, summary, form')) return;
    if (window.getSelection()?.toString().trim()) return;
    const target = row.querySelector('[data-row-target]');
    if (!target) return;
    if ((event.ctrlKey || event.metaKey) && target.href) window.open(target.href, '_blank', 'noopener');
    else target.click();
});


/*
 * Moving around without reloads. Links and GET forms in the app (sidebar, tabs, filters, search, page
 * numbers, rows) load the next page in the background and swap the content in place, with the sidebar
 * and the month shown in it. Back and Forward work the same way. Downloads, print pages, other sites and
 * anything that isn't an app page (a sign-in page, an error) get a normal page load.
 */
function isSoftTarget(url, from) {
    if (!document.getElementById('main') || url.origin !== location.origin) return false;
    if (/\/(file|export|print|payslip|template)(\/|$)|^\/(login|logout|api)(\/|$)/.test(url.pathname)) return false;
    return Boolean(from) && !from.closest('[data-drawer-source], #drawer, [data-no-soft]');
}

let softRequest = 0;

// Show the new page: scroll to its #hash, keep the place on the same page, start at the top of a new one.
function showSoftPage(doc, url, push, quiet = false) {
    // Tabs, page numbers and filters (quiet) keep your place; a new page starts at the top.
    const samePage = quiet || url.pathname === location.pathname;
    // Filters, search, tabs and page numbers stay out of the address bar; the history entry keeps them.
    const state = { soft: true, fetch: url.origin + url.pathname + url.search };
    if (push) history.pushState(state, '', cleanAddress(url));
    else history.replaceState(state, '', cleanAddress(url));
    currentFetch = state.fetch;
    setSidebar(false);
    swapPage(doc, url.href, (main) => {
        const target = url.hash && main.querySelector('#' + CSS.escape(url.hash.slice(1)));
        if (target) {
            target.setAttribute('tabindex', '-1');
            target.focus({ preventScroll: true });
            target.scrollIntoView({ block: 'start' });
        } else if (!samePage) {
            window.scrollTo(0, 0);
        }
        restoreSearch(main);
    }, !quiet);
}

async function softNavigate(url, push = true, quiet = false) {
    const id = ++softRequest;
    showLoader();
    try {
        const { response, doc } = await fetchPage(url.href);
        if (id !== softRequest) return;
        if (!response.ok || !doc.getElementById('main')) { location.assign(response.ok ? response.url : url.href); return; }
        const final = new URL(response.url);
        final.hash = url.hash;
        showSoftPage(doc, final, push, quiet);
    } catch (e) {
        location.assign(url.href);
    } finally {
        if (id === softRequest) hideLoader();
    }
}

document.addEventListener('click', (event) => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    const a = event.target.closest('a[href]');
    if (!a || a.getAttribute('target') || a.hasAttribute('download') || a.hasAttribute('data-drawer')) return;
    // getAttribute, not .href: links inside SVG charts have no string .href.
    const url = new URL(a.getAttribute('href'), location.href);
    // Only the #hash differs: let the browser jump to it.
    const shown = new URL(currentFetch);
    if (url.pathname === shown.pathname && url.search === shown.search && url.hash) return;
    if (!isSoftTarget(url, a)) return;
    event.preventDefault();
    // Tabs, page numbers and links to the same page update the content in place: no fade, no jump.
    const inPlace = url.pathname === location.pathname || Boolean(a.closest('#main .tabs, #main nav[aria-label="Pages"]'));
    softNavigate(url, true, inPlace);
});

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (event.defaultPrevented || (form.getAttribute('method') || 'get').toLowerCase() !== 'get' || form.hasAttribute('data-upload')) return;
    const url = new URL(form.action || location.href);
    if (!isSoftTarget(url, form)) return;
    event.preventDefault();
    url.search = new URLSearchParams(new FormData(form, event.submitter)).toString();
    // Typing in a search box: swap without the fade, so the list keeps up with the typing.
    const typing = document.activeElement?.matches?.('input[type="search"]') && form.contains(document.activeElement);
    rememberSearch(typing ? document.activeElement : null);
    // Filters and searches in the page update it in place; the sidebar month picker reloads the view with a fade.
    softNavigate(url, true, Boolean(form.closest('#main')));
}, true);

// A page opened with filters in its address (a link from outside, a redirect) shows the clean address too.
if (document.getElementById('main')) {
    // Refreshing (F5) keeps the history entry, filters included: show the page with them again.
    const saved = history.state?.fetch;
    history.replaceState({ ...(history.state || {}), soft: true, fetch: currentFetch }, '', cleanAddress(new URL(location.href)));
    if (saved && saved !== currentFetch && new URL(saved).pathname === location.pathname) {
        const url = new URL(saved);
        url.hash = location.hash;
        softNavigate(url, false, true);
    }
}
window.addEventListener('popstate', (event) => {
    // A Back/Forward that only changes the #hash is the browser's own jump.
    const fetchUrl = event.state?.fetch;
    if (!event.state?.soft || !fetchUrl || fetchUrl === currentFetch) return;
    const url = new URL(fetchUrl);
    url.hash = location.hash;
    softNavigate(url, false);
});

/* Live search: results update while you type (after a short pause), without a reload. The box keeps
 * focus and the cursor; if you kept typing while results loaded, it searches again for the newer text. */
let searchTimer;
let searchState = null;
function rememberSearch(input) {
    searchState = input ? { id: input.id, name: input.name, sent: input.value } : null;
}
function restoreSearch(main) {
    if (!searchState) return;
    const state = searchState;
    searchState = null;
    const input = (state.id && main.querySelector('#' + CSS.escape(state.id))) || main.querySelector(`input[type="search"][name="${state.name}"]`);
    if (!input) return;
    const live = state.typed ?? state.sent;
    input.value = live;
    input.focus({ preventScroll: true });
    input.setSelectionRange(live.length, live.length);
    if (live !== state.sent) queueSearch(input);
}
function queueSearch(input) {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        if (input.isConnected && input.form) input.form.requestSubmit();
    }, 350);
}
document.addEventListener('input', (event) => {
    const input = event.target;
    if (!input.matches?.('#main form input[type="search"]') || (input.form?.getAttribute('method') || 'get').toLowerCase() !== 'get') return;
    // Text typed while the last search is still loading is carried over to the new page.
    if (searchState && searchState.name === input.name) searchState.typed = input.value;
    else queueSearch(input);
});

/* Refresh button in each page banner: loads the same page again in the background. */
document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-refresh]');
    if (!button) return;
    softNavigate(new URL(currentFetch), false);
});
