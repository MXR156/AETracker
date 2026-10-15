<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected bool $signIn = false;

    private function user(array $attrs = []): User
    {
        return User::create(array_merge(['name' => 'Mike', 'email' => 'mike@example.com', 'password' => 'correct-horse', 'is_admin' => false], $attrs));
    }

    public function test_personal_and_editing_pages_need_a_login(): void
    {
        foreach (['/import', '/my-parcels/planner', '/projection', '/settings', '/account', '/users', '/locations/create'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->post('/locations', ['name' => 'Sneaky', 'type' => 'village'])->assertRedirect('/login');
        $this->assertSame(0, Location::count());
        $this->get('/up')->assertOk();
        $this->get('/login')->assertOk()->assertSee('There are no accounts yet')->assertDontSee('Create an account');
    }

    public function test_logging_in_and_out(): void
    {
        $user = $this->user();

        $this->post('/login', ['email' => 'mike@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->get('/projection'); // remembered, so you land there after logging in
        $this->post('/login', ['email' => 'mike@example.com', 'password' => 'correct-horse'])->assertRedirect('/projection');
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->get('/')->assertOk()->assertSee('Mike')->assertSee('Log out');

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_too_many_wrong_passwords_are_slowed_down(): void
    {
        $this->user();
        foreach (range(1, 5) as $i) {
            $this->post('/login', ['email' => 'mike@example.com', 'password' => 'wrong']);
        }
        $this->post('/login', ['email' => 'mike@example.com', 'password' => 'correct-horse'])->assertSessionHasErrors('email');
        $this->assertMatchesRegularExpression('/^Too many attempts\. Try again in \d+ seconds\.$/', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_bookmarklet_import_waits_for_login_then_continues(): void
    {
        $this->user();
        $payload = json_encode(['tab' => 'city', 'place' => 'boreham, ess, eng', 'description' => 'Top 500 biggest boreham Landowners',
            'rows' => [['username' => 'SuperSparrow', 'parcels' => 120]]]);

        $review = $this->post('/import/atlas-earth', ['payload' => $payload])->assertRedirect()->headers->get('Location');
        $this->get($review)->assertRedirect('/login');
        $this->post('/login', ['email' => 'mike@example.com', 'password' => 'correct-horse'])->assertRedirect($review);
        $this->get($review)->assertOk()->assertSee('Boreham');
    }

    public function test_only_admins_manage_users(): void
    {
        $member = $this->user();
        $this->actingAs($member)->get('/users')->assertForbidden();
        $this->actingAs($member)->post('/users', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'password1'])->assertForbidden();
        $this->actingAs($member)->get('/')->assertDontSee('href="'.route('users.index').'"', false);

        $admin = $this->user(['name' => 'Admin', 'email' => 'admin@example.com', 'is_admin' => true]);
        $this->actingAs($admin)->get('/users')->assertOk()->assertSee('mike@example.com');

        $this->actingAs($admin)->post('/users', ['name' => 'Friend', 'email' => 'friend@example.com', 'password' => 'short'])->assertSessionHasErrors('password');
        $this->actingAs($admin)->post('/users', ['name' => 'Friend', 'email' => 'friend@example.com', 'password' => 'long-enough'])->assertSessionHasNoErrors();
        $friend = User::where('email', 'friend@example.com')->firstOrFail();
        $this->assertFalse($friend->is_admin);

        // Reset a password, promote, remove.
        $this->actingAs($admin)->put("/users/{$friend->id}", ['password' => 'brand-new-pass'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('brand-new-pass', $friend->fresh()->password));
        $this->actingAs($admin)->put("/users/{$member->id}", ['is_admin' => 1]);
        $this->assertTrue($member->fresh()->is_admin);
        $this->actingAs($admin)->delete("/users/{$friend->id}")->assertRedirect('/users');
        $this->assertNull($friend->fresh());

        // You can't delete yourself, and there must always be an admin.
        $this->actingAs($admin)->delete("/users/{$admin->id}")->assertSessionHasErrors('user');
        $this->actingAs($admin)->put("/users/{$member->id}", ['is_admin' => 0]);
        $this->actingAs($admin)->put("/users/{$admin->id}", ['is_admin' => 0])->assertSessionHasErrors('is_admin');
        $this->assertTrue($admin->fresh()->is_admin);
    }

    public function test_changing_my_password_needs_the_current_one(): void
    {
        $user = $this->user();
        $this->actingAs($user)->put('/account', ['name' => 'Mike', 'email' => 'mike@example.com', 'current_password' => 'nope',
            'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])->assertSessionHasErrors('current_password');
        $this->actingAs($user)->put('/account', ['name' => 'Mike R', 'email' => 'mike@example.com', 'current_password' => 'correct-horse',
            'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('new-password-1', $user->fresh()->password));
        $this->assertSame('Mike R', $user->fresh()->name);
    }

    public function test_command_creates_the_first_admin_and_resets_passwords(): void
    {
        $this->artisan('aetracker:user', ['email' => 'Me@Example.com', '--password' => 'first-pass-1'])->assertSuccessful();
        $me = User::firstOrFail();
        $this->assertSame('me@example.com', $me->email);
        $this->assertTrue($me->is_admin); // the first account is always an admin

        $this->artisan('aetracker:user', ['email' => 'other@example.com', '--password' => 'other-pass-1'])->assertSuccessful();
        $this->assertFalse(User::where('email', 'other@example.com')->first()->is_admin);

        $this->artisan('aetracker:user', ['email' => 'me@example.com', '--password' => 'second-pass'])->assertSuccessful();
        $this->assertTrue(Hash::check('second-pass', $me->fresh()->password));
        $this->assertSame(2, User::count());

        $this->artisan('aetracker:user', ['email' => 'bad@example.com', '--password' => 'short'])->assertFailed();
    }

    public function test_export_mysql_writes_insert_statements(): void
    {
        Location::create(['name' => "O'Brien's Field", 'type' => 'village']);
        $path = tempnam(sys_get_temp_dir(), 'aet-sql-');

        $this->artisan('aetracker:export-mysql', ['path' => $path])->assertSuccessful();
        $sql = file_get_contents($path);
        unlink($path);

        $this->assertStringContainsString('SET FOREIGN_KEY_CHECKS = 0;', $sql);
        $this->assertStringContainsString('INSERT INTO `locations`', $sql);
        $this->assertStringContainsString("'O\\'Brien\\'s Field'", $sql);
        $this->assertStringContainsString('ALTER TABLE `locations` AUTO_INCREMENT = 2;', $sql);
    }
}
