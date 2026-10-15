<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Player;
use App\Models\Snapshot;
use App\Services\Leaderboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaderboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_location_and_snapshot_flow(): void
    {
        $this->post('/locations', ['name' => 'Boreham', 'type' => 'village', 'region' => 'Essex'])
            ->assertRedirect();
        $location = Location::firstOrFail();

        $this->post("/locations/{$location->id}/snapshots", [
            'recorded_on' => '2026-07-08',
            'rows' => [
                ['username' => 'SuperSparrow', 'parcels' => 100],
                ['username' => 'LogisticHawk', 'parcels' => 82],
                ['username' => '', 'parcels' => ''],
            ],
            'paste' => "Kage_uvlf 20\nBlocksta 15",
        ])->assertRedirect("/locations/{$location->id}")->assertRedirect()->assertSessionHasNoErrors();

        auth()->user()->update(['player_id' => Player::where('username', 'SuperSparrow')->value('id')]);

        // Second snapshot: LogisticHawk overtakes, Blocksta leaves, a newcomer arrives.
        $this->post("/locations/{$location->id}/snapshots", [
            'recorded_on' => '2026-08-08',
            'rows' => [
                ['username' => 'SuperSparrow', 'parcels' => 104],
                ['username' => 'logistichawk', 'parcels' => 110],
                ['username' => 'Kage_uvlf', 'parcels' => 20],
                ['username' => 'Blocksta', 'parcels' => 0],
            ],
            'paste' => 'Newbie 3',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(5, Player::count()); // "logistichawk" matched the existing player

        $board = new Leaderboard($location->fresh());
        $me = Player::me();

        $this->assertSame('LogisticHawk', $board->leader()->player->username);
        $this->assertSame(28, $board->leader()->change);
        $this->assertSame(7, $board->parcelsToLead($me));
        $this->assertSame(2, $board->rowFor($me)->rank);
        $this->assertSame(4, $board->rowFor($me)->since_first);
        $this->assertTrue($board->rows->firstWhere('player.username', 'Newbie')->is_new);
        $this->assertSame(['Blocksta'], $board->dropped()->pluck('player.username')->all());

        $this->get("/locations/{$location->id}")
            ->assertOk()
            ->assertSee('Mayor of Boreham')
            ->assertSee('LogisticHawk')
            ->assertSee('You need');

        // A second snapshot on the same date is rejected.
        $this->post("/locations/{$location->id}/snapshots", [
            'recorded_on' => '2026-08-08',
            'rows' => [['username' => 'SuperSparrow', 'parcels' => 1]],
        ])->assertSessionHasErrors('recorded_on');

        // Editing keeps its own date.
        $snapshot = Snapshot::where('recorded_on', '2026-08-08')->firstOrFail();
        $this->put("/snapshots/{$snapshot->id}", [
            'recorded_on' => '2026-08-08',
            'rows' => [['username' => 'SuperSparrow', 'parcels' => 120]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $board = new Leaderboard($location->fresh());
        $this->assertSame('SuperSparrow', $board->leader()->player->username);
        $this->assertSame(0, $board->parcelsToLead($me));

        foreach (['/', '/locations', '/locations?filter=mayor', '/players', "/players/{$me->id}", "/locations/{$location->id}/snapshots/create", "/snapshots/{$snapshot->id}/edit"] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_ties_share_the_top_rank(): void
    {
        $location = Location::create(['name' => 'Terling', 'type' => 'village']);
        $snapshot = $location->snapshots()->create(['recorded_on' => '2026-07-08']);
        foreach (['A' => 6, 'B' => 6, 'C' => 1] as $name => $parcels) {
            $snapshot->entries()->create(['player_id' => Player::findOrCreateByName($name)->id, 'parcels' => $parcels]);
        }

        $board = new Leaderboard($location);

        $this->assertCount(2, $board->leaders());
        $this->assertSame(3, $board->rows->last()->rank);
        $this->assertSame(1, $board->parcelsToLead(Player::where('username', 'A')->first()));
    }
}
