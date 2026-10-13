<?php

namespace Tests\Feature;

use App\Http\Controllers\SnapshotController;
use App\Models\Location;
use App\Models\Player;
use App\Services\Leaderboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnonymousPlayersTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_anonymous_is_a_separate_ghost_per_location(): void
    {
        $boreham = Location::create(['name' => 'Boreham', 'type' => 'village']);
        $sandon = Location::create(['name' => 'Sandon', 'type' => 'village']);

        $this->post("/locations/{$boreham->id}/snapshots", [
            'recorded_on' => '2026-10-01',
            'rows' => [
                ['username' => 'SuperSparrow', 'parcels' => 100],
                ['username' => 'Anonymous', 'parcels' => 5],
                ['username' => 'anonymous', 'parcels' => 12],
            ],
            'paste' => 'Anonymous 3',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->post("/locations/{$sandon->id}/snapshots", [
            'recorded_on' => '2026-10-01',
            'rows' => [['username' => 'Anonymous', 'parcels' => 7]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $board = new Leaderboard($boreham->fresh());
        $this->assertSame(
            ['SuperSparrow' => 100, 'Anonymous #1' => 12, 'Anonymous #2' => 5, 'Anonymous #3' => 3],
            $board->rows->mapWithKeys(fn ($r) => [$r->player->username => $r->parcels])->all(),
        );
        // Sandon's Anonymous #1 is a different player from Boreham's.
        $this->assertSame(2, Player::where('username', 'Anonymous #1')->count());
        $this->assertSame(1, Player::real()->count());

        // The next snapshot is pre-filled with the numbered ghosts, so their history carries on.
        $this->get("/locations/{$boreham->id}/snapshots/create")->assertOk()->assertSee('Anonymous #2');

        $this->post("/locations/{$boreham->id}/snapshots", [
            'recorded_on' => '2026-10-10',
            'rows' => [
                ['username' => 'SuperSparrow', 'parcels' => 101],
                ['username' => 'Anonymous #1', 'parcels' => 15],
                ['username' => 'Anonymous #2', 'parcels' => 0],
                ['username' => 'Anonymous #3', 'parcels' => 4],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $board = new Leaderboard($boreham->fresh());
        $one = $board->rows->firstWhere('player.username', 'Anonymous #1');
        $this->assertSame(3, $one->change);
        $this->assertSame(['Anonymous #2'], $board->dropped()->pluck('player.username')->all());

        $this->get('/players')->assertOk()->assertDontSee('Anonymous #1');
        $this->get('/players?anonymous=1')->assertOk()->assertSee('Anonymous #1');
        $this->get("/players/{$one->player->id}")->assertOk()->assertDontSee('This is me');

        // Deleting the location removes its ghosts too.
        $boreham->delete();
        $this->assertSame(1, Player::whereNotNull('location_id')->count());
    }

    public function test_split_entries_numbering(): void
    {
        [$named, $ghosts] = SnapshotController::splitEntries([
            ['Bob', 10], ['anon', 2], ['Anonymous #1', 9], ['Anonymous', 20], ['bob', 11], ['Anonymous #4', 0],
        ]);

        $this->assertSame(['bob' => ['bob', 11]], $named);
        // #1 is taken explicitly, so the unnamed ones fill #2 and #3 biggest first; #4 on 0 parcels is dropped.
        $this->assertSame([1 => 9, 2 => 20, 3 => 2], $ghosts);
    }
}
