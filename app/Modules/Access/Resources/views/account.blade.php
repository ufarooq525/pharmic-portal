<x-layouts.app title="My account" description="Change your password.">
    <x-page-header title="My account" :sub="auth()->user()->name.' · '.auth()->user()->email.' · '.auth()->user()->roleLabel()" />

    <section id="password-form" class="card max-w-[560px]" aria-labelledby="pw-h">
        <h2 id="pw-h" class="card-title">Change password</h2>
        <form method="post" data-ajax action="{{ route('account.password') }}" class="grid gap-4">
            @csrf
            @method('put')
            <div class="flex flex-col gap-1.5">
                <label for="pw-current" class="label">Current password</label>
                <x-password id="pw-current" name="current_password" required autocomplete="current-password" />
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="pw-new" class="label">New password (at least 10 characters)</label>
                <x-password id="pw-new" name="password" required minlength="10" autocomplete="new-password" />
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="pw-confirm" class="label">New password again</label>
                <x-password id="pw-confirm" name="password_confirmation" required minlength="10" autocomplete="new-password" />
            </div>
            <div class="flex gap-2"><button class="btn btn-primary" data-busy-label="Saving…">Change password</button></div>
        </form>
        <p class="m-0 text-[12px] text-muted">Changing it signs you out on your other browsers and devices.</p>
    </section>
</x-layouts.app>
