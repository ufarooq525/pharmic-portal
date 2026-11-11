<x-layouts.app title="Users & roles">
    <x-page-header title="Users and roles" sub="Each person signs in with their own account. Their role decides what they can open and change." />

    <section class="card flex flex-col gap-4" aria-labelledby="users-h">
        <h2 id="users-h" class="card-title">Users</h2>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Role</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Save</span></th></tr></thead>
                <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td class="font-bold">{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td colspan="3">
                            <form method="post" action="{{ route('users.update', $user) }}" class="flex flex-wrap items-center gap-2">
                                @csrf @method('put')
                                <label for="role-{{ $user->id }}" class="sr-only">Role for {{ $user->name }}</label>
                                <select id="role-{{ $user->id }}" name="role" class="field min-h-9 w-auto py-1">
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->value }}" @selected($user->role === $role)>{{ $role->label() }}</option>
                                    @endforeach
                                </select>
                                <label for="active-{{ $user->id }}" class="sr-only">Status for {{ $user->name }}</label>
                                <select id="active-{{ $user->id }}" name="is_active" class="field min-h-9 w-auto py-1">
                                    <option value="1" @selected($user->is_active)>Active</option>
                                    <option value="0" @selected(! $user->is_active)>Deactivated</option>
                                </select>
                                <button class="btn btn-sm">Save</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="card flex flex-col gap-4" aria-labelledby="add-h">
        <h2 id="add-h" class="card-title">Add a user</h2>
        <form method="post" action="{{ route('users.store') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
            @csrf
            <div class="flex flex-col gap-1.5"><label for="n" class="label">Name</label><input id="n" name="name" value="{{ old('name') }}" required class="field"></div>
            <div class="flex flex-col gap-1.5"><label for="e" class="label">Email</label><input id="e" name="email" type="email" value="{{ old('email') }}" required class="field"></div>
            <div class="flex flex-col gap-1.5"><label for="r" class="label">Role</label>
                <select id="r" name="role" class="field">
                    @foreach ($roles as $role)<option value="{{ $role->value }}" @selected(old('role') === $role->value)>{{ $role->label() }}</option>@endforeach
                </select>
            </div>
            <div class="flex flex-col gap-1.5"><label for="p" class="label">Temporary password</label><input id="p" name="password" type="password" minlength="10" required autocomplete="new-password" class="field"></div>
            <button class="btn btn-primary">Add user</button>
        </form>
    </section>

    <section class="card flex flex-col gap-4" aria-labelledby="perm-h">
        <h2 id="perm-h" class="card-title">What each role can do</h2>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th scope="col">Action</th>@foreach ($roles as $role)<th scope="col">{{ $role->label() }}</th>@endforeach</tr></thead>
                <tbody>
                @foreach ($permissions as $action => $allowed)
                    <tr>
                        <td>{{ $action }}</td>
                        @foreach ($roles as $role)
                            <td>
                                @if (in_array($role->value, $allowed, true))
                                    <x-chip tone="ok">Yes</x-chip>
                                @else
                                    <x-chip tone="mute">No</x-chip>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-[13px] text-muted">Medical reps get their own territory view (customers, sales, incentive, payslip) in phase 2.</p>
    </section>
</x-layouts.app>
