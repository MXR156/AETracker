<?php

namespace Tests\Feature;

use App\Models\BoostRegion;
use App\Models\Holding;
use App\Models\Location;
use App\Models\Player;
use App\Models\Setting;
use App\Models\User;
use App\Services\AccountAdoption;
use App\Services\EarningsCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Shared leaderboards, private personal data, admin-only deletes and settings, per-country tiers. */
class AccountsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $signIn = false;

    private function member(string $name): User
    {
        return User::create(['name' => $name, 'email' => strtolower($name).'@example.com', 'password' => 'password-123']);
    }

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password-123', 'is_admin' => true]);
    }

    public function test_personal_data_is_private_to_each_account(): void
    {
        $alice = $this->member('Alice');
        $bob = $this->member('Bob');

        $this->actingAs($alice)->post('/my-parcels/holdings', ['common' => 400, 'rare' => 20, 'epic' => 5, 'legendary' => 1]);
        $this->actingAs($alice)->put('/my-parcels/badges', ['badges_owned' => 57]);
        $this->actingAs($bob)->post('/my-parcels/holdings', ['common' => 10, 'rare' => 0, 'epic' => 0, 'legendary' => 0]);

        $this->assertSame(426, Holding::current($alice)->total());
        $this->assertSame(10, Holding::current($bob)->total());
        $this->assertSame('57', Setting::allValues($alice)['badges_owned']);
        $this->assertSame('0', Setting::allValues($bob)['badges_owned']);

        // Bob's dashboard shows his numbers, not Alice's.
        $html = $this->actingAs($bob)->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('<div class="value">10</div>', $html);      // his parcels
        $this->assertStringNotContainsString('<div class="value">426</div>', $html);  // not hers
        $this->assertStringNotContainsString('value="57"', $html);                    // nor her badges

        // Bob can't delete Alice's history.
        $aliceEntry = Holding::current($alice);
        $this->actingAs($bob)->delete("/my-parcels/holdings/{$aliceEntry->id}")->assertNotFound();
        $this->assertNotNull($aliceEntry->fresh());

        // Projection settings are per account too.
        $this->actingAs($alice)->put('/projection', [
            'login_streak_day' => 10, 'login_cycle_days' => 90, 'login_free_base' => 1, 'login_explorer_base' => 90,
            'explorer_club' => '1', 'explorer_cost_month' => 20, 'premium_cost_month' => 0, 'ab_extra_month' => 0, 'ab_balance' => 0,
            'projection_months' => 12, 'projection_basis' => 'rewards',
        ])->assertSessionHasNoErrors();
        $this->assertSame('1', Setting::allValues($alice)['explorer_club']);
        $this->assertSame('0', Setting::allValues($bob)['explorer_club']);
    }

    public function test_each_account_has_its_own_me_and_a_player_can_only_be_claimed_once(): void
    {
        $alice = $this->member('Alice');
        $bob = $this->member('Bob');
        $sparrow = Player::create(['username' => 'SuperSparrow']);
        $hawk = Player::create(['username' => 'LogisticHawk']);

        $this->actingAs($alice)->post("/players/{$sparrow->id}/me")->assertSessionHasNoErrors();
        $this->assertTrue($alice->fresh()->player->is($sparrow));

        // Bob can't take it, and isn't told whose it is; he doesn't even get the button.
        $this->actingAs($bob)->get("/players/{$sparrow->id}")->assertOk()->assertDontSee('This is me')->assertDontSee('Alice');
        $this->actingAs($bob)->post("/players/{$sparrow->id}/me")->assertSessionHasErrors('player');
        $this->assertNull($bob->fresh()->player_id);
        $this->actingAs($bob)->post("/players/{$hawk->id}/me")->assertSessionHasNoErrors();

        // Each sees their own "me".
        $this->actingAs($alice->fresh())->get('/')->assertSee('Tracking as')->assertSee('SuperSparrow');
        $this->actingAs($bob->fresh())->get('/')->assertSee('LogisticHawk');

        // Alice lets it go; an admin can also unlink without seeing which player it was.
        $this->actingAs($alice->fresh())->delete("/players/{$sparrow->id}/me");
        $this->assertNull($alice->fresh()->player_id);
        $admin = $this->admin();
        $this->actingAs($admin)->get('/users')->assertOk()->assertSee('linked')->assertDontSee('LogisticHawk');
        $this->actingAs($admin)->delete("/users/{$bob->id}/player");
        $this->assertNull($bob->fresh()->player_id);
    }

    public function test_members_add_and_edit_but_only_admins_delete_or_change_settings(): void
    {
        $member = $this->member('Alice');
        $this->actingAs($member)->post('/locations', ['name' => 'Boreham', 'type' => 'village'])->assertRedirect();
        $location = Location::firstOrFail();
        $this->actingAs($member)->post("/locations/{$location->id}/snapshots", ['recorded_on' => '2026-10-01', 'paste' => 'SuperSparrow 100'])
            ->assertSessionHasNoErrors();
        $this->actingAs($member)->put("/locations/{$location->id}", ['name' => 'Boreham', 'type' => 'village', 'region' => 'Essex'])->assertRedirect();
        $snapshot = $location->snapshots()->first();
        $player = Player::firstOrFail();

        // No deleting, no settings, no delete buttons.
        $this->actingAs($member)->delete("/locations/{$location->id}")->assertForbidden();
        $this->actingAs($member)->delete("/snapshots/{$snapshot->id}")->assertForbidden();
        $this->actingAs($member)->delete("/players/{$player->id}")->assertForbidden();
        $this->actingAs($member)->get('/settings')->assertForbidden();
        $this->actingAs($member)->put('/settings', config('aetracker.defaults'))->assertForbidden();
        $this->actingAs($member)->get("/locations/{$location->id}")->assertOk()->assertDontSee('Delete');
        $this->actingAs($member)->get("/locations/{$location->id}/edit")->assertOk()->assertDontSee('Delete location');
        $this->assertNotNull($snapshot->fresh());

        $admin = $this->admin();
        $this->actingAs($admin)->get("/locations/{$location->id}")->assertSee('Delete');
        $this->actingAs($admin)->delete("/snapshots/{$snapshot->id}")->assertRedirect();
        $this->actingAs($admin)->delete("/locations/{$location->id}")->assertRedirect();
        $this->assertSame(0, Location::count());
    }

    public function test_boost_tiers_follow_each_accounts_country(): void
    {
        $uk = BoostRegion::firstOrFail(); // the migration's "United Kingdom" default
        $this->assertSame('United Kingdom', $uk->name);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/settings/countries', ['name' => 'France', 'boost_tiers' => "1:20\n61:15", 'badge_tiers' => '1:3'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->put("/settings/countries/{$uk->id}", ['name' => 'United Kingdom', 'boost_tiers' => '1:25', 'badge_tiers' => '1:5'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/settings/countries', ['name' => 'Nowhere', 'boost_tiers' => 'rubbish'])->assertSessionHasErrors('boost_tiers');
        $france = BoostRegion::where('name', 'France')->firstOrFail();

        $brit = $this->member('Brit');
        $french = $this->member('Pierre');
        $this->actingAs($french)->put('/account/preferences', ['boost_region_id' => $france->id, 'boost_hours_per_day' => 12])->assertSessionHasNoErrors();
        $this->actingAs($french)->put('/settings/countries/'.$france->id, ['name' => 'X', 'boost_tiers' => '1:99'])->assertForbidden();

        // Brit hasn't picked, so gets the default (UK); Pierre gets France and his own boosted hours.
        $calc = fn (User $u) => (new EarningsCalculator(Setting::allValues($u)))->calculate(['common' => 10], 1);
        $this->assertSame(25.0, $calc($brit)['boost_multiplier']);
        $this->assertSame(20.0, $calc($french)['boost_multiplier']);
        $this->assertSame(5.0, $calc($brit)['badge_percent']);
        $this->assertSame(3.0, $calc($french)['badge_percent']);
        $this->assertSame(12.0, $calc($french)['boost_hours']);
        $this->assertSame(24.0, $calc($brit)['boost_hours']);
        $this->actingAs($french)->get('/')->assertSee('France');

        // Removing a country moves its users back to the default; the last one can't go.
        $this->actingAs($admin)->delete("/settings/countries/{$france->id}")->assertRedirect();
        $this->assertSame('United Kingdom', Setting::allValues($french)['boost_region_name']);
        $this->actingAs($admin)->delete("/settings/countries/{$uk->id}")->assertSessionHasErrors('region');
    }

    public function test_data_from_before_accounts_goes_to_the_first_account(): void
    {
        // As left by the upgrade when nobody had an account yet.
        $sparrow = Player::create(['username' => 'SuperSparrow']);
        DB::table('holdings')->insert(['user_id' => null, 'recorded_on' => '2026-10-01', 'common' => 234, 'rare' => 104, 'epic' => 63, 'legendary' => 23]);
        DB::table('settings')->insert([['key' => 'badges_owned', 'value' => '57'], ['key' => AccountAdoption::LEGACY_ME, 'value' => (string) $sparrow->id]]);
        $this->assertTrue(AccountAdoption::hasOrphans());

        $this->artisan('aetracker:user', ['email' => 'mike@example.com', '--password' => 'password-123'])->assertSuccessful();
        $mike = User::firstOrFail();

        $this->assertTrue($mike->is_admin);
        $this->assertTrue($mike->player->is($sparrow));
        $this->assertSame(424, Holding::current($mike)->total());
        $this->assertSame('57', Setting::allValues($mike)['badges_owned']);
        $this->assertFalse(AccountAdoption::hasOrphans());

        // The next account starts empty.
        $this->artisan('aetracker:user', ['email' => 'friend@example.com', '--password' => 'password-123'])->assertSuccessful();
        $friend = User::where('email', 'friend@example.com')->first();
        $this->assertNull(Holding::current($friend));
        $this->assertNull($friend->player_id);
    }
}
