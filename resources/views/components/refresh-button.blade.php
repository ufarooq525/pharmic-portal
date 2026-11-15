{{-- Loads this page's figures again in the background (no reload); the icon spins while it works. --}}
<button type="button" {{ $attributes->class(['btn btn-icon']) }} data-refresh aria-label="Refresh this page’s data" title="Refresh">
    <x-icon name="refresh" :size="18" />
</button>
