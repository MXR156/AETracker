<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login', ['noUsers' => User::count() === 0, 'canRegister' => self::registrationOpen()]);
    }

    /**
     * Sign-up is open if an admin has allowed it - and only once an admin account exists, so a
     * stranger can't be first to register on a new install.
     */
    public static function registrationOpen(): bool
    {
        return Setting::allValues()['allow_registration'] === '1' && User::where('is_admin', true)->exists();
    }

    public function showRegister()
    {
        abort_unless(self::registrationOpen(), 404);

        return view('auth.register');
    }

    public function register(Request $request)
    {
        abort_unless(self::registrationOpen(), 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create($data + ['is_admin' => false, 'last_login_at' => now()]);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', "Welcome, {$user->name}! Start by finding your player and clicking “This is me”, then enter your parcels below.");
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // 5 wrong attempts per email + IP, then a minute's wait.
        $key = 'login:'.Str::lower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password']], $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'Those details don\'t match an account.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You\'ve been logged out.');
    }
}
