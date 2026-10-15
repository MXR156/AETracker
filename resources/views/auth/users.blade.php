@extends('layouts.app')
@section('title', 'Users')
@section('content')
<div class="page-head">
    <div><h1>Users</h1><div class="muted">Who can log in. There's no sign-up page - only accounts added here can get in.</div></div>
</div>

<div class="card table-wrap">
    <table>
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th class="hide-sm">Last login</th><th>Reset password</th><th></th></tr></thead>
        <tbody>
        @foreach ($users as $u)
            <tr @class(['me' => $u->is(auth()->user())])>
                <td>{{ $u->name }} @if ($u->is(auth()->user()))<span class="chip">you</span>@endif</td>
                <td>{{ $u->email }}</td>
                <td>
                    <form method="post" action="{{ route('users.update', $u) }}" class="inline">
                        @csrf @method('PUT')
                        <input type="hidden" name="is_admin" value="{{ $u->is_admin ? 0 : 1 }}">
                        <span @class(['chip', 'chip-gold' => $u->is_admin, 'chip-muted' => ! $u->is_admin])>{{ $u->is_admin ? 'Admin' : 'Member' }}</span>
                        <button class="link-btn small" style="margin-left:.4rem">{{ $u->is_admin ? 'Make member' : 'Make admin' }}</button>
                    </form>
                </td>
                <td class="hide-sm small muted">{{ $u->last_login_at?->diffForHumans() ?? 'never' }}</td>
                <td>
                    <form method="post" action="{{ route('users.update', $u) }}" class="actions" style="flex-wrap:nowrap">
                        @csrf @method('PUT')
                        <input type="password" name="password" placeholder="New password" minlength="8" required style="max-width:160px" autocomplete="new-password">
                        <button class="btn btn-ghost btn-sm">Set</button>
                    </form>
                </td>
                <td class="right">
                    @unless ($u->is(auth()->user()))
                        <form method="post" action="{{ route('users.destroy', $u) }}" class="inline" onsubmit="return confirm('Remove {{ $u->name }}? They won\'t be able to log in.')">
                            @csrf @method('DELETE')<button class="link-btn" style="color:var(--down)">Remove</button>
                        </form>
                    @endunless
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <p class="muted small" style="margin-bottom:0">Members can use everything except this page. Admins can also add and remove people.</p>
</div>

<div class="card" style="max-width:640px">
    <h2>Add someone</h2>
    <form method="post" action="{{ route('users.store') }}">
        @csrf
        <div class="form-row">
            <div class="field"><label for="new_name">Name</label><input id="new_name" name="name" value="{{ old('name') }}" required></div>
            <div class="field"><label for="new_email">Email</label><input type="email" id="new_email" name="email" value="{{ old('email') }}" required></div>
        </div>
        <div class="field"><label for="new_password">Password</label>
            <input type="password" id="new_password" name="password" minlength="8" required autocomplete="new-password">
            <div class="hint">At least 8 characters. Send it to them; they can change it under their name at the top.</div></div>
        <div class="field"><label style="font-weight:400"><input type="checkbox" name="is_admin" value="1"> Admin (can manage users)</label></div>
        <button class="btn">Add user</button>
    </form>
</div>
@endsection
