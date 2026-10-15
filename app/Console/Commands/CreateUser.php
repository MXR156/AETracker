<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AccountAdoption;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/** Add an account (or reset its password) from the command line - how the first admin is made. */
class CreateUser extends Command
{
    protected $signature = 'aetracker:user
        {email : Email address to log in with}
        {--name= : Display name (defaults to the part of the email before the @)}
        {--admin : Make this account an admin (can manage users)}
        {--password= : Password (you\'ll be asked for one if left out)}';

    protected $description = 'Create a login (or reset an existing one\'s password)';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        $user = User::where('email', $email)->first();
        $password = $this->option('password') ?? $this->secret($user ? "New password for {$email}" : "Password for {$email}");

        $validator = Validator::make(['email' => $email, 'password' => $password], [
            'email' => ['required', 'email'],
            'password' => ['required', Password::min(8)],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user ??= new User(['email' => $email, 'name' => $this->option('name') ?: strstr($email, '@', true)]);
        if ($this->option('name')) {
            $user->name = $this->option('name');
        }
        $user->password = $password;
        $first = User::count() === 0;
        $user->is_admin = $user->is_admin || $this->option('admin') || $first; // the first account is always an admin
        $user->save();

        // The first account takes over the parcels, badges, projection and "me" player from before accounts.
        if ($first && AccountAdoption::hasOrphans()) {
            AccountAdoption::adopt($user->id);
            $this->line('Your existing parcels, badges, projection settings and “me” player are now in this account.');
        }

        $this->info(($user->wasRecentlyCreated ? 'Created' : 'Updated')." {$user->email}".($user->is_admin ? ' (admin)' : '').'.');

        return self::SUCCESS;
    }
}
