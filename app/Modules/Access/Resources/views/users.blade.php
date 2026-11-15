@php
    use App\Modules\Access\Enums\LoginType;

    $distributorNames = $distributors->pluck('name', 'id');
    $dataOf = function ($u) use ($distributorNames) {
        if ($u->isMedicalRep()) {
            return $u->medicalRep ? 'Own customers in '.$u->medicalRep->territory->name : 'No rep linked';
        }
        $ids = $u->visibleDistributorIds();

        return $ids === null ? 'All distributors' : collect($ids)->map(fn ($id) => $distributorNames[$id] ?? '?')->join(', ');
    };
    $total = count(\App\Modules\Access\Enums\Permission::cases());
    $mine = $actor->permissionValues();
    $canGive = fn (string $p) => $actor->isOwner() || in_array($p, $mine, true);
@endphp
<x-layouts.app title="Users & roles" description="Who can sign in, what each role can do, and which data each person sees.">
    <x-page-header title="Users and roles" sub="A role is a set of permissions. Each person gets one role, plus any extra permissions you tick for them." />

    <nav class="tabs" aria-label="Users and roles">
        <a href="{{ route('users.index') }}" class="tab" @if ($tab === 'people') aria-current="page" @endif>People <span class="text-muted">{{ $users->total() }}</span></a>
        <a href="{{ route('users.index', ['tab' => 'roles']) }}" class="tab" @if ($tab === 'roles') aria-current="page" @endif>Roles <span class="text-muted">{{ $roles->count() }}</span></a>
    </nav>

    @if ($tab === 'roles')
        <section class="card" aria-labelledby="roles-h">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 id="roles-h" class="card-title">Roles</h2>
                    <p class="m-0 text-[13px] text-muted">Changing a role changes it for everyone who has it, from their next page.</p>
                </div>
                <a href="#role-form" data-drawer="#role-form" data-drawer-title="Add a role" class="btn btn-primary btn-sm">+ Add role</a>
            </div>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th scope="col">Role</th><th scope="col" class="r">People</th><th scope="col">Permissions</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody>
                    @foreach ($rolesPage as $r)
                        @php $count = count($rolePermissions[$r->id]); @endphp
                        <tr>
                            <td>
                                <span class="font-bold">{{ $r->name }}</span>
                                @if ($r->description)<span class="block text-[12.5px] text-muted">{{ $r->description }}</span>@endif
                            </td>
                            <td class="num r">{{ $r->users_count }}</td>
                            <td>
                                @if ($r->is_owner)
                                    <x-chip tone="ok">Everything</x-chip>
                                @else
                                    <x-chip :tone="$count ? 'info' : 'mute'">{{ $count }} of {{ $total }}</x-chip>
                                @endif
                            </td>
                            <td class="text-right whitespace-nowrap">
                                @if ($r->is_owner)
                                    <span class="text-[12px] text-muted">Can’t be changed</span>
                                @else
                                    <a href="{{ route('users.index', ['tab' => 'roles', 'role' => $r->id]) }}#role-form" data-drawer="#role-form" data-drawer-title="Edit {{ $r->name }}" class="btn btn-sm" aria-label="Edit role {{ $r->name }}" data-row-target>Edit</a>
                                    @if ($r->users_count === 0)
                                        <form method="post" action="{{ route('users.roles.destroy', $r) }}" data-ajax data-confirm="Delete the role “{{ $r->name }}”? Nobody has it, so nobody loses access." class="inline">
                                            @csrf
                                            @method('delete')
                                            <button class="btn btn-sm" data-busy-label="Deleting…" aria-label="Delete role {{ $r->name }}">Delete</button>
                                        </form>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $rolesPage->links() }}
        </section>

        <section id="role-form" class="card scroll-mt-4" aria-labelledby="rf-h" data-drawer-source>
            <h2 id="rf-h" class="card-title">{{ $editRole ? 'Edit '.$editRole->name : 'Add a role' }}</h2>
            <form method="post" action="{{ $editRole ? route('users.roles.update', $editRole) : route('users.roles.store') }}" class="flex flex-col gap-4" data-permission-form>
                @csrf
                @if ($editRole) @method('put') @endif
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5"><label for="r-n" class="label">Name</label><input id="r-n" name="name" value="{{ old('name', $editRole?->name) }}" required maxlength="50" class="field" placeholder="e.g. Stock clerk"></div>
                    <div class="flex flex-col gap-1.5"><label for="r-d" class="label">What it’s for (optional)</label><input id="r-d" name="description" value="{{ old('description', $editRole?->description) }}" maxlength="150" class="field" placeholder="One line, shown in the roles list"></div>
                </div>
                @include('access::_permissions', ['mode' => 'role', 'ticked' => old('permissions', $editRole ? $rolePermissions[$editRole->id] : []), 'fromRole' => [], 'canGive' => $canGive])
                @if ($editRole && $editRole->users_count)
                    <p class="m-0 text-[13px] text-muted">{{ $editRole->users_count === 1 ? '1 person has' : $editRole->users_count.' people have' }} this role: {{ $editRolePeople->join(', ') }}.</p>
                @endif
                <div class="flex gap-2">
                    <button class="btn btn-primary" data-busy-label="Saving…">{{ $editRole ? 'Save role' : 'Add role' }}</button>
                    <button type="button" class="btn" data-drawer-close>Cancel</button>
                </div>
            </form>
        </section>
    @else
        <section class="card" aria-labelledby="users-h">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="users-h" class="card-title">People</h2>
                <a href="#user-form" data-drawer="#user-form" data-drawer-title="Add a person" class="btn btn-primary btn-sm">+ Add person</a>
            </div>
            <div class="table-wrap">
                <table class="data">
                    <thead><tr><th scope="col">Name</th><th scope="col">Login</th><th scope="col">Role</th><th scope="col">Sees</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Edit</span></th></tr></thead>
                    <tbody>
                    @foreach ($users as $u)
                        @php $extras = count($u->extraPermissionValues()); @endphp
                        <tr>
                            <td class="font-bold">{{ $u->name }}</td>
                            <td class="num text-[13px]">{{ $u->email }}</td>
                            <td>
                                <span class="flex flex-wrap items-center gap-1.5">
                                    <x-chip :tone="$u->isOwner() ? 'ok' : ($u->isMedicalRep() ? 'warn' : 'info')">{{ $u->roleLabel() }}</x-chip>
                                    @if ($extras)<span class="text-[12px] font-semibold text-muted">+ {{ $extras }} extra</span>@endif
                                </span>
                            </td>
                            <td class="text-[13px]">{{ $dataOf($u) }}</td>
                            <td><x-chip :tone="$u->is_active ? 'ok' : 'mute'">{{ $u->is_active ? 'Active' : 'Deactivated' }}</x-chip></td>
                            <td class="text-right">
                                @if ($actor->isOwner() || ! $u->isOwner())
                                    <a href="{{ route('users.index', ['edit' => $u->id]) }}#user-form" data-drawer="#user-form" data-drawer-title="Edit {{ $u->name }}" class="btn btn-sm" aria-label="Edit {{ $u->name }}" data-row-target>Edit</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $users->links() }}
        </section>

        @php
            $type = old('login_type', $editUser?->login_type?->value ?? 'staff');
            $roleId = (int) old('role_id', $editUser?->role_id ?? $roles->firstWhere('name', 'Viewer')?->id ?? $roles->firstWhere('is_owner', false)?->id);
        @endphp
        <section id="user-form" class="card scroll-mt-4" aria-labelledby="uf-h" data-drawer-source>
            <h2 id="uf-h" class="card-title">{{ $editUser ? 'Edit '.$editUser->name : 'Add a person' }}</h2>
            <form method="post" action="{{ $editUser ? route('users.update', $editUser) : route('users.store') }}" class="flex flex-col gap-5" data-permission-form data-role-permissions='@json($rolePermissions)' data-owner-roles='@json($roles->where('is_owner', true)->pluck('id')->values())'>
                @csrf
                @if ($editUser) @method('put') @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    @unless ($editUser)
                        <div class="flex flex-col gap-1.5"><label for="u-n" class="label">Name</label><input id="u-n" name="name" value="{{ old('name') }}" required maxlength="100" class="field" autocomplete="off"></div>
                        <div class="flex flex-col gap-1.5"><label for="u-e" class="label">Email (their login)</label><input id="u-e" name="email" type="email" value="{{ old('email') }}" required class="field" autocomplete="off"></div>
                        <div class="flex flex-col gap-1.5 sm:col-span-2"><label for="u-p" class="label">Temporary password (at least 10 characters)</label><input id="u-p" name="password" type="password" minlength="10" required autocomplete="new-password" class="field"></div>
                    @else
                        <div class="flex flex-col gap-1.5">
                            <label for="u-pw" class="label">New password (leave empty to keep)</label>
                            <input id="u-pw" name="password" type="password" minlength="10" autocomplete="new-password" class="field">
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label for="u-a" class="label">Status</label>
                            <select id="u-a" name="is_active" class="field">
                                <option value="1" @selected($editUser->is_active)>Active: can sign in</option>
                                <option value="0" @selected(! $editUser->is_active)>Deactivated: cannot sign in</option>
                            </select>
                        </div>
                    @endunless
                </div>

                <fieldset class="m-0 flex flex-col gap-2 border-0 p-0">
                    <legend class="label mb-1">Type of login</legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach (LoginType::cases() as $lt)
                            <label class="choice">
                                <input type="radio" name="login_type" value="{{ $lt->value }}" @checked($type === $lt->value) data-login-type>
                                <span><b>{{ $lt->label() }}</b><span class="block text-[12.5px] text-muted">{{ $lt === LoginType::Staff ? 'Gets a role and works in the portal.' : 'Sees only their own customers, sales and payslip.' }}</span></span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="flex flex-col gap-5" data-for-type="staff" @if ($type !== 'staff') hidden @endif>
                    <div class="flex flex-col gap-1.5">
                        <label for="u-r" class="label">Role</label>
                        <select id="u-r" name="role_id" class="field" data-role-select>
                            @foreach ($roles as $r)
                                @if (! $r->is_owner || $actor->isOwner())
                                    <option value="{{ $r->id }}" @selected($roleId === $r->id)>{{ $r->name }}{{ $r->description ? ' – '.$r->description : '' }}</option>
                                @endif
                            @endforeach
                        </select>
                        <a href="{{ route('users.index', ['tab' => 'roles']) }}" class="self-start text-[12.5px]">Change what roles can do</a>
                    </div>

                    @include('access::_permissions', [
                        'mode' => 'person',
                        'ticked' => old('permissions', $editUser?->extraPermissionValues() ?? []),
                        'fromRole' => $rolePermissions[$roleId] ?? [],
                        'isOwnerRole' => (bool) $roles->firstWhere('id', $roleId)?->is_owner,
                        'canGive' => $canGive,
                    ])

                    <fieldset class="m-0 flex flex-col gap-2 border-0 p-0" data-hide-for-owner @if ($roles->firstWhere('id', $roleId)?->is_owner) hidden @endif>
                        <legend class="label mb-1">Which distributors they see</legend>
                        <p class="m-0 text-[12.5px] text-muted">Leave all unticked to see every distributor.</p>
                        <div class="flex flex-wrap gap-x-5 gap-y-2">
                            @php $picked = array_map('intval', old('distributor_ids', $editUser?->distributor_ids ?? [])); @endphp
                            @foreach ($distributors as $d)
                                <label class="flex min-h-9 items-center gap-2"><input type="checkbox" name="distributor_ids[]" value="{{ $d->id }}" @checked(in_array($d->id, $picked, true)) class="size-4"> {{ $d->name }}</label>
                            @endforeach
                        </div>
                    </fieldset>
                </div>

                <div class="flex flex-col gap-1.5" data-for-type="medical_rep" @if ($type !== 'medical_rep') hidden @endif>
                    <label for="u-rep" class="label">Which medical rep is this?</label>
                    <select id="u-rep" name="medical_rep_id" class="field">
                        <option value="">Choose a rep…</option>
                        @foreach ($reps as $rep)
                            <option value="{{ $rep->id }}" @selected((int) old('medical_rep_id', $editUser?->medical_rep_id) === $rep->id)>{{ $rep->name }} · {{ $rep->territory->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex gap-2">
                    <button class="btn btn-primary" data-busy-label="Saving…">{{ $editUser ? 'Save' : 'Add person' }}</button>
                    <button type="button" class="btn" data-drawer-close>Cancel</button>
                </div>
            </form>
        </section>
    @endif
</x-layouts.app>
