<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/** Admins choose who can log in. */
class UserController extends Controller
{
    public function index()
    {
        return view('auth.users', ['users' => User::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', Password::min(8)],
        ]);
        $data['is_admin'] = $request->boolean('is_admin');
        User::create($data);

        return redirect()->route('users.index')->with('status', "Added {$data['name']}. Send them the password so they can log in.");
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'password' => ['nullable', Password::min(8)],
            'is_admin' => ['nullable', 'boolean'],
        ]);

        if ($request->has('is_admin')) {
            $admin = $request->boolean('is_admin');
            if (! $admin && $user->is_admin && User::admins() === 1) {
                throw ValidationException::withMessages(['is_admin' => 'There has to be at least one admin.']);
            }
            $user->is_admin = $admin;
        }
        if (filled($data['password'] ?? null)) {
            $user->password = $data['password'];
            $user->setRememberToken(null); // log them out of "remember me" devices
        }
        $user->save();

        return redirect()->route('users.index')->with('status', "Updated {$user->name}.");
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'You can\'t delete your own account.']);
        }
        if ($user->is_admin && User::admins() === 1) {
            return back()->withErrors(['user' => 'There has to be at least one admin.']);
        }
        $user->delete();

        return redirect()->route('users.index')->with('status', "Removed {$user->name}. They can no longer log in.");
    }
}
