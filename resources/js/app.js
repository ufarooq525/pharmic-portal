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

    const input = document.getElementById(button.dataset.passwordToggle);
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    button.setAttribute('aria-pressed', String(show));
    button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    button.querySelector('[data-eye="show"]')?.classList.toggle('hidden', show);
    button.querySelector('[data-eye="hide"]')?.classList.toggle('hidden', !show);
    input.focus();
});

/* Submit buttons lock after the first click, so a slow page can't post twice. */
function lockButtons(form, busy) {
    form.querySelectorAll('button:not([type="button"]), input[type="submit"]').forEach((button) => {
        if (busy) {
            button.dataset.idleLabel ??= button.textContent;
            const label = button.dataset.busyLabel || button.textContent.trim();
            button.replaceChildren(Object.assign(document.createElement('span'), { className: 'spinner', ariaHidden: 'true' }), document.createTextNode(' ' + label));
        } else if (button.dataset.idleLabel !== undefined) {
            button.textContent = button.dataset.idleLabel;
        }
        button.disabled = busy;
    });
    form.toggleAttribute('aria-busy', busy);
}

document.addEventListener('submit', (event) => {
    const form = event.target;
    const question = form.dataset.confirm;

    if (question && !window.confirm(question)) {
        event.preventDefault();
        return;
    }
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
const loader = document.getElementById('page-loader');
let loaderTimer;
function showLoader() {
    if (!loader) return;
    clearTimeout(loaderTimer);
    // Fast pages never flash the bar; it appears only if loading takes over 120 ms.
    loaderTimer = setTimeout(() => { loader.dataset.active = ''; document.body.setAttribute('aria-busy', 'true'); }, 120);
}
function hideLoader() {
    clearTimeout(loaderTimer);
    if (loader) delete loader.dataset.active;
    document.body.removeAttribute('aria-busy');
}

const isPageLink = (a, event) => a && a.href && !a.target && !a.hasAttribute('download')
    && a.origin === location.origin && !(a.pathname === location.pathname && a.hash)
    && !(event && (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0))
    && !/\/(file|export|print|payslip|template)(\?|$|\/)/.test(a.pathname);

document.addEventListener('click', (event) => {
    const a = event.target.closest('a');
    if (!event.defaultPrevented && isPageLink(a, event)) showLoader();
});

/* Speed: start fetching a page when the pointer rests on its link, so the click opens it almost at once. */
const prefetched = new Set();
let hoverTimer;
function prefetch(a) {
    if (!isPageLink(a) || prefetched.has(a.href) || prefetched.size > 40 || navigator.connection?.saveData) return;
    prefetched.add(a.href);
    document.head.appendChild(Object.assign(document.createElement('link'), { rel: 'prefetch', href: a.href, as: 'document' }));
}
document.addEventListener('mouseover', (event) => {
    const a = event.target.closest('a');
    if (!a) return;
    clearTimeout(hoverTimer);
    hoverTimer = setTimeout(() => prefetch(a), 80);
});
document.addEventListener('mouseout', () => clearTimeout(hoverTimer));
document.addEventListener('touchstart', (event) => prefetch(event.target.closest('a')), { passive: true });

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
            window.location.assign(body.redirect);
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

/* Toast */
function toast(message, tone = 'ok') {
    const box = document.getElementById('toast');
    if (!box || !message) return;
    box.textContent = message;
    box.dataset.tone = tone;
    box.hidden = false;
    clearTimeout(box.timer);
    box.timer = setTimeout(() => { box.hidden = true; }, 4200);
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

function openDrawer(section, title) {
    const copy = section.cloneNode(true);
    copy.removeAttribute('id');
    copy.removeAttribute('data-drawer-source');
    // The hidden original stays on the page, so give the copy's ids a prefix to keep labels working.
    copy.querySelectorAll('[id]').forEach((el) => { el.id = 'dw-' + el.id; });
    copy.querySelectorAll('label[for]').forEach((el) => { el.htmlFor = 'dw-' + el.htmlFor; });
    copy.querySelectorAll('[list]').forEach((el) => el.setAttribute('list', 'dw-' + el.getAttribute('list')));
    const heading = copy.querySelector('h2, .card-title');
    drawerTitle.textContent = title || heading?.textContent.trim() || '';
    heading?.remove();
    drawerBody.replaceChildren(...copy.childNodes);
    drawer.hidden = false;
    requestAnimationFrame(() => drawer.dataset.open = '');
    document.body.style.overflow = 'hidden';
    drawerBody.querySelector('input:not([type=hidden]), select, textarea')?.focus();
}

function closeDrawer() {
    if (!drawer || drawer.hidden) return;
    delete drawer.dataset.open;
    document.body.style.overflow = '';
    setTimeout(() => { drawer.hidden = true; drawerBody.replaceChildren(); }, 200);
    drawerReturnFocus?.focus();
}

async function fetchPage(url, options = {}) {
    const response = await fetch(url, { credentials: 'same-origin', ...options, headers: { 'X-Requested-With': 'XMLHttpRequest', ...(options.headers || {}) } });
    return { response, doc: new DOMParser().parseFromString(await response.text(), 'text/html') };
}

// Swap in the main content (and sidebar badges) from a freshly loaded page, without a full reload.
function swapPage(doc, url) {
    const main = doc.getElementById('main');
    if (!main) { location.assign(url); return; }
    document.getElementById('main').replaceWith(main);
    const nav = doc.querySelector('#sidebar nav');
    if (nav) document.querySelector('#sidebar nav')?.replaceWith(nav);
    document.title = doc.title;
    if (url && url !== location.href) history.replaceState(null, '', url);
    main.querySelectorAll('form[data-upload]').forEach(setupUpload);
    const status = main.querySelector('[role=status]');
    const problem = main.querySelector('[role=alert]');
    if (status) { toast(status.textContent.trim()); status.remove(); }
    else if (problem) problem.scrollIntoView({ block: 'center', behavior: 'smooth' });
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

    showLoader();
    try {
        const { doc } = await fetchPage(link.href);
        const section = doc.querySelector(selector);
        section ? openDrawer(section, link.dataset.drawerTitle) : location.assign(link.href);
    } catch (e) {
        location.assign(link.href);
    } finally {
        hideLoader();
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') { closeDrawer(); setSidebar(false); }
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
        if (inDrawer) form.prepend(Object.assign(document.createElement('div'), { className: 'rounded-[14px] bg-bad-soft px-3.5 py-3 font-semibold text-bad', textContent: message, dataset: { formProblem: '' } }));
        else toast(message, 'bad');
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

        if (inDrawer) closeDrawer();
        hideLoader();
        swapPage(doc, response.url);
    } catch (e) {
        fail('Could not save. Check the connection and try again.');
    }
}
