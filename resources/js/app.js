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
            if (button.dataset.busyLabel) button.textContent = button.dataset.busyLabel;
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
    if (event.defaultPrevented || form.hasAttribute('data-upload') || form.method.toLowerCase() === 'get') return;

    // After the browser has collected the form data, so the clicked button's value still posts.
    setTimeout(() => lockButtons(form, true), 0);
});

// Coming back with the browser's Back button shows the cached page: unlock it.
window.addEventListener('pageshow', (event) => {
    if (event.persisted) document.querySelectorAll('form[aria-busy]').forEach((form) => lockButtons(form, false));
});

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
            await wait(500);
            window.location.assign(body.redirect);
        });
        xhr.addEventListener('error', () => showErrors(['The upload stopped. Check the connection and try again.']));

        xhr.send(new FormData(form));
    });
}

document.querySelectorAll('form[data-upload]').forEach(setupUpload);
