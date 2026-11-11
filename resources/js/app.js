/*
 * The only JavaScript the portal needs: the theme toggle and auto-submitting filter forms.
 * Everything else is server-rendered Blade, so pages work without JS.
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
