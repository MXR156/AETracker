<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function edit(Request $request)
    {
        return view('auth.account', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'current_password' => ['required_with:password', 'nullable', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ], ['current_password.current_password' => 'Your current password is wrong.']);

        $user->fill(['name' => $data['name'], 'email' => $data['email']]);
        if (filled($data['password'] ?? null)) {
            $user->password = $data['password'];
        }
        $user->save();

        return redirect()->route('account.edit')->with('status', 'Account updated.');
    }
}
