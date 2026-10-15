<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Admins manage who can log in, the game settings, and deleting shared leaderboard data.
        Gate::define('admin', fn (User $user) => $user->is_admin);

        // Behind an HTTPS proxy (e.g. Cloudflare) the app itself is reached over plain http, so it
        // would build http:// links - including the bookmarklet's address. If APP_URL is https,
        // always build https links.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
