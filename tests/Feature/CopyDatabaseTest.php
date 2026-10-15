<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Player;
use App\Models\Setting;
use App\Services\Leaderboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CopyDatabaseTest extends TestCase
{
    use RefreshDatabase;

    /** A SQLite file with some tracker data in it, made with the app's own migrations. */
    private function makeSourceFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'aet-src-');
        config(['database.connections.src' => ['driver' => 'sqlite', 'database' => $path, 'prefix' => '', 'foreign_key_constraints' => true]]);
        Artisan::call('migrate', ['--database' => 'src', '--force' => true]);

        $db = DB::connection('src');
        $db->table('locations')->insert(['id' => 5, 'name' => 'Boreham', 'type' => 'village']);
        $db->table('players')->insert([
            ['id' => 10, 'username' => 'SuperSparrow', 'location_id' => null],
            ['id' => 11, 'username' => 'Anonymous #1', 'location_id' => 5],
        ]);
        $db->table('snapshots')->insert(['id' => 3, 'location_id' => 5, 'recorded_on' => '2026-10-01']);
        $db->table('snapshot_entries')->insert([
            ['snapshot_id' => 3, 'player_id' => 10, 'parcels' => 118],
            ['snapshot_id' => 3, 'player_id' => 11, 'parcels' => 4],
        ]);
        $db->table('boost_regions')->update(['boost_tiers' => '1:30']); // the source's own UK tiers
        $db->table('boost_regions')->insert(['name' => 'France', 'boost_tiers' => '1:20', 'badge_tiers' => '1:3']);
        $db->table('holdings')->insert(['recorded_on' => '2026-10-01', 'common' => 234, 'rare' => 104, 'epic' => 63, 'legendary' => 23]);
        // As an older database ends up once brought up to date: personal data with no account yet.
        $db->table('settings')->insert([
            ['key' => 'badges_owned', 'value' => '57'], ['key' => 'explorer_club', 'value' => '1'],
            ['key' => 'legacy_me_player_id', 'value' => '10'], ['key' => 'rent_common', 'value' => '0.00000000111'],
        ]);
        DB::purge('src');

        return $path;
    }

    public function test_copies_everything_and_keeps_ids_and_links(): void
    {
        $path = $this->makeSourceFile();
        $before = md5_file($path);

        $this->artisan('aetracker:copy-database', ['path' => $path])->assertSuccessful();

        $this->assertSame($before, md5_file($path)); // the SQLite file isn't changed
        $boreham = Location::findOrFail(5);
        $board = new Leaderboard($boreham);
        $this->assertSame('SuperSparrow', $board->leader()->player->username);
        // The personal data went to the (only) admin - the logged-in tester.
        auth()->user()->refresh();
        $this->assertTrue(Player::me()->is($board->leader()->player));
        $this->assertSame('1', Setting::allValues()['explorer_club']);
        $this->assertSame('0.00000000111', Setting::allValues()['rent_common']); // shared game setting stays shared
        // Countries come from the copy, replacing the new database's default one.
        $this->assertSame(['France', 'United Kingdom'], \App\Models\BoostRegion::orderBy('name')->pluck('name')->all());
        $this->assertSame('1:30', Setting::allValues()['boost_tiers']);
        $this->assertSame(5, Player::where('username', 'Anonymous #1')->value('location_id'));
        $this->assertSame('57', Setting::allValues()['badges_owned']);
        $this->assertSame(424, \App\Models\Holding::current()->total());

        // New rows carry on after the copied IDs.
        $this->assertGreaterThan(11, Player::create(['username' => 'Newcomer'])->id);

        // A second copy is refused unless --replace is given.
        $this->artisan('aetracker:copy-database', ['path' => $path])->assertFailed();
        $this->artisan('aetracker:copy-database', ['path' => $path, '--replace' => true])->assertSuccessful();
        $this->assertSame(2, Player::count()); // "Newcomer" was replaced along with everything else
        $this->assertSame(1, \App\Models\User::count()); // the SQLite file had no logins, so the existing one is kept
        unlink($path);
    }

    public function test_rejects_a_missing_file(): void
    {
        $this->artisan('aetracker:copy-database', ['path' => '/nope/missing.sqlite'])->assertFailed();
    }
}
