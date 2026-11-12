@php
    $tones = ['Full' => 'ok', 'Edit' => 'info', 'View' => 'mute', 'None' => 'bad', 'Own territory' => 'warn'];
    $distributorNames = $distributors->pluck('name', 'id');
    $scopeOf = function ($u) use ($distributorNames) {
        if ($u->role?->value === 'medical_rep') {
            return $u->medicalRep ? $u->medicalRep->territory->name.' only' : 'No rep linked';
        }
        $ids = $u->visibleDistributorIds();

        return $ids === null ? 'All distributors' : collect($ids)->map(fn ($id) => $distributorNames[$id] ?? '?')->join(', ');
    };
    $editUser = $users->firstWhere('id', $editing);
@endphp
<x-layouts.app title="Users & roles" description="Logins, roles and which data each person can see.">
    <x-page-header title="Users and roles" sub="Each person has their own login. The role decides what they can open and change; the scope decides which data they see." />

    <nav class="tabs" aria-label="Users and roles">
        <a href="{{ route('users.index') }}" class="tab" @if ($tab === 'users') aria-current="page" @endif>Users</a>
        <a href="{{ route('users.index', ['tab' => 'permissions']) }}" class="tab" @if ($tab === 'permissions') aria-current="page" @endif>Role permissions</a>
    </nav>

    @if ($tab === 'permissions')
        <section class="card" aria-labelledby="perm-h">
            <h2 id="perm-h" class="card-title">Role permissions</h2>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th scope="col">Module or action</th>@foreach ($roles as $role)<th scope="col">{{ $role->label() }}</th>@endforeach</tr></thead>
                    <tbody>
                    @foreach ($permissions as $action => $levels)
                        <tr>
                            <td>{{ $action }}</td>
                            @foreach ($levels as $level)
                                <td><x-chip :tone="$tones[$level]">{{ $level }}</x-chip></td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <p class="m-0 text-[12px] text-muted">“Own territory” means a medical rep sees only their own customers, sales, incentive and payslip on their “My territory” page. Any role except Owner can also be limited to some distributors.</p>
        </section>
    @else
        <section class="card" aria-labelledby="users-h">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="users-h" class="card-title">Users</h2>
                <a href="#user-form" data-drawer="#user-form" data-drawer-title="Add a user" class="btn btn-primary btn-sm">+ Add user</a>
            </div>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th scope="col">Name</th><th scope="col">Login</th><th scope="col">Role</th><th scope="col">Data scope</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Edit</span></th></tr></thead>
                    <tbody>
                    @foreach ($users as $u)
                        <tr>
                            <td class="font-bold">{{ $u->name }}</td>
                            <td class="num text-[13px]">{{ $u->email }}</td>
                            <td>{{ $u->role?->label() }}</td>
                            <td>{{ $scopeOf($u) }}</td>
                            <td><x-chip :tone="$u->is_active ? 'ok' : 'mute'">{{ $u->is_active ? 'Active' : 'Deactivated' }}</x-chip></td>
                            <td class="text-right"><a href="{{ route('users.index', ['edit' => $u->id]) }}#user-form" data-drawer="#user-form" class="btn btn-sm">Edit</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section id="user-form" class="card scroll-mt-4" aria-labelledby="uf-h" data-drawer-source>
            <h2 id="uf-h" class="card-title">{{ $editUser ? 'Edit '.$editUser->name : 'Add a user' }}</h2>
            <form method="post" action="{{ $editUser ? route('users.update', $editUser) : route('users.store') }}" class="grid gap-4 sm:grid-cols-[repeat(auto-fit,minmax(200px,1fr))]">
                @csrf
                @if ($editUser) @method('put') @endif
                @unless ($editUser)
                    <div class="flex flex-col gap-1.5"><label for="u-n" class="label">Name</label><input id="u-n" name="name" value="{{ old('name') }}" required maxlength="100" class="field"></div>
                    <div class="flex flex-col gap-1.5"><label for="u-e" class="label">Email (login)</label><input id="u-e" name="email" type="email" value="{{ old('email') }}" required class="field"></div>
                    <div class="flex flex-col gap-1.5"><label for="u-p" class="label">Temporary password</label><input id="u-p" name="password" type="password" minlength="10" required autocomplete="new-password" class="field"></div>
                @endunless
                <div class="flex flex-col gap-1.5">
                    <label for="u-r" class="label">Role</label>
                    <select id="u-r" name="role" class="field">
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}" @selected(old('role', $editUser?->role?->value ?? 'viewer') === $role->value)>{{ $role->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="u-rep" class="label">Medical rep record (for the Medical rep role)</label>
                    <select id="u-rep" name="medical_rep_id" class="field">
                        <option value="">–</option>
                        @foreach ($reps as $rep)
                            <option value="{{ $rep->id }}" @selected((int) old('medical_rep_id', $editUser?->medical_rep_id) === $rep->id)>{{ $rep->name }} · {{ $rep->territory->name }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($editUser)
                    <div class="flex flex-col gap-1.5">
                        <label for="u-pw" class="label">New password (leave empty to keep)</label>
                        <input id="u-pw" name="password" type="password" minlength="10" autocomplete="new-password" class="field">
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="u-a" class="label">Status</label>
                        <select id="u-a" name="is_active" class="field">
                            <option value="1" @selected($editUser->is_active)>Active</option>
                            <option value="0" @selected(! $editUser->is_active)>Deactivated</option>
                        </select>
                    </div>
                @endif
                <fieldset class="m-0 flex flex-col gap-2 border-0 p-0 sm:col-span-full">
                    <legend class="label mb-1">Limit to these distributors (leave all unticked for all distributors)</legend>
                    <div class="flex flex-wrap gap-x-5 gap-y-2">
                        @php($picked = array_map('intval', old('distributor_ids', $editUser?->distributor_ids ?? [])))
                        @foreach ($distributors as $d)
                            <label class="flex items-center gap-2"><input type="checkbox" name="distributor_ids[]" value="{{ $d->id }}" @checked(in_array($d->id, $picked, true)) class="size-4"> {{ $d->name }}</label>
                        @endforeach
                    </div>
                </fieldset>
                <div class="flex gap-2 sm:col-span-full">
                    <button class="btn btn-primary">{{ $editUser ? 'Save' : 'Add user' }}</button>
                    <button type="button" class="btn" data-drawer-close>Cancel</button>
                </div>
            </form>
        </section>
    @endif
</x-layouts.app>
