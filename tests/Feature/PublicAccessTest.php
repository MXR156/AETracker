<?php

namespace Tests\Feature;

use App\Models\Holding;
use App\Models\Location;
use App\Models\Player;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Visitors can browse the shared leaderboards; accounts track their own stats. */
class PublicAccessTest extends TestCase
{
    use RefreshDatabase;

    protected bool $signIn = false;

    private function seedBoard(): array
    {
        $admin = User::create(['name' => 'Mike', 'email' => 'mike@example.com', 'password' => 'password-123', 'is_admin' => true]);
        $boreham = Location::create(['name' => 'Boreham', 'type' => 'village']);
        foreach ([['2026-09-01', 100, 80], ['2026-10-01', 118, 121]] as [$date, $sparrow, $hawk]) {
            $s = $boreham->snapshots()->create(['recorded_on' => $date]);
            $s->entries()->create(['player_id' => Player::findOrCreateByName('SuperSparrow')->id, 'parcels' => $sparrow]);
            $s->entries()->create(['player_id' => Player::findOrCreateByName('LogisticHawk')->id, 'parcels' => $hawk]);
        }
        $admin->update(['player_id' => Player::where('username', 'SuperSparrow')->value('id')]);
        Holding::create(['user_id' => $admin->id, 'recorded_on' => '2026-10-01', 'common' => 9999]);
        Setting::put('badges_owned', '57', $admin);

        return [$admin, $boreham];
    }

    public function test_visitors_see_the_public_home_page_and_leaderboards(): void
    {
        [, $boreham] = $this->seedBoard();
        $hawk = Player::where('username', 'LogisticHawk')->first();

        $this->get('/')->assertOk()
            ->assertSee('Who owns Atlas Earth?')->assertSee('Log in')->assertSee('Create an account')
            ->assertSee('Mayors')->assertSee('LogisticHawk')                   // mayor of Boreham
            ->assertSee('Biggest landowners')->assertSee('Biggest recent gains')->assertSee('+41')
            ->assertDontSee('9,999')->assertDontSee('How many badges');        // nobody's private stats

        $this->get('/locations')->assertOk()->assertSee('Boreham')->assertDontSee('+ Add location');
        $this->get("/locations/{$boreham->id}")->assertOk()->assertSee('Mayor of Boreham')
            ->assertDontSee('+ Add snapshot')->assertDontSee('>Edit<', false)->assertDontSee('You need');
        $this->get('/players')->assertOk()->assertSee('SuperSparrow');
        $this->get("/players/{$hawk->id}")->assertOk()->assertDontSee('This is me')->assertDontSee('Edit player');
        // Which account a player belongs to isn't shown to visitors either.
        $this->get('/players/'.Player::where('username', 'SuperSparrow')->value('id'))->assertOk()->assertDontSee('Mike');

        // ...but they can't change anything.
        $this->post('/locations', ['name' => 'Sneaky', 'type' => 'village'])->assertRedirect('/login');
        $this->put("/locations/{$boreham->id}", ['name' => 'Hacked', 'type' => 'village'])->assertRedirect('/login');
        $this->post("/players/{$hawk->id}/me")->assertRedirect('/login');
        $this->assertSame('Boreham', $boreham->fresh()->name);
    }

    public function test_registering_gives_you_your_own_private_stats(): void
    {
        [$admin] = $this->seedBoard();

        $this->get('/register')->assertOk();
        $this->post('/register', ['name' => 'Pierre', 'email' => 'pierre@example.com', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors('password');
        $this->post('/register', ['name' => 'Pierre', 'email' => 'mike@example.com', 'password' => 'long-enough', 'password_confirmation' => 'long-enough'])
            ->assertSessionHasErrors('email');
        $this->post('/register', ['name' => 'Pierre', 'email' => 'pierre@example.com', 'password' => 'long-enough', 'password_confirmation' => 'long-enough'])
            ->assertRedirect('/');

        $pierre = User::where('email', 'pierre@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($pierre);
        $this->assertFalse($pierre->is_admin);
        $this->get('/')->assertOk()->assertSee('Welcome, Pierre')->assertSee('How many badges do you have?')
            ->assertDontSee('9,999')->assertDontSee('value="57"', false);
        $this->get('/users')->assertForbidden();

        // He can now add to the shared leaderboards and claim his own player.
        $this->post('/locations', ['name' => 'Sandon', 'type' => 'village'])->assertRedirect();
        $this->post('/players/'.Player::where('username', 'LogisticHawk')->value('id').'/me')->assertSessionHasNoErrors();
        $this->assertSame('LogisticHawk', $pierre->fresh()->player->username);
        $this->assertSame('SuperSparrow', $admin->fresh()->player->username);
    }

    public function test_admins_can_turn_sign_up_off(): void
    {
        [$admin] = $this->seedBoard();

        $this->actingAs($admin)->put('/settings', array_merge(array_diff_key(config('aetracker.defaults'), array_flip(config('aetracker.page_settings'))), ['allow_registration' => '0']))
            ->assertSessionHasNoErrors();
        $this->assertSame('0', Setting::allValues()['allow_registration']);
        auth()->logout();

        $this->get('/')->assertOk()->assertDontSee('Create an account');
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'long-enough', 'password_confirmation' => 'long-enough'])->assertNotFound();
        $this->assertSame(1, User::count());
    }

    public function test_sign_up_stays_closed_until_an_admin_exists(): void
    {
        // A fresh install: nobody should be able to register and become the first (admin) account.
        $this->get('/')->assertOk()->assertDontSee('Create an account');
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'Eve', 'email' => 'eve@example.com', 'password' => 'long-enough', 'password_confirmation' => 'long-enough'])->assertNotFound();
        $this->assertSame(0, User::count());
    }
}
