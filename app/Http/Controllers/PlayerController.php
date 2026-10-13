<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Player;
use App\Services\Leaderboard;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlayerController extends Controller
{
    /** Each player's current position in every location they appear in (from the latest snapshots). */
    private function positions(): array
    {
        $positions = [];
        foreach (Location::orderBy('name')->get() as $location) {
            $board = new Leaderboard($location);
            foreach ($board->rows as $row) {
                $positions[$row->player->id][] = (object) [
                    'location' => $location,
                    'board' => $board,
                    'row' => $row,
                    'is_leader' => $row->rank === 1,
                ];
            }
        }

        return $positions;
    }

    public function index(Request $request)
    {
        $positions = $this->positions();
        $me = Player::me();
        $myLocations = $me ? collect($positions[$me->id] ?? [])->pluck('location.id')->all() : [];

        $players = Player::query()
            ->when(trim((string) $request->query('q')), fn ($q, $s) => $q->where('username', 'like', "%{$s}%"))
            ->get()
            ->map(function ($player) use ($positions, $myLocations) {
                $mine = collect($positions[$player->id] ?? []);

                return (object) [
                    'player' => $player,
                    'locations' => $mine,
                    'total' => $mine->sum(fn ($p) => $p->row->parcels),
                    'leads' => $mine->where('is_leader', true)->count(),
                    'shared' => $player->is_me ? null : $mine->whereIn('location.id', $myLocations)->count(),
                ];
            })
            ->when($request->query('min'), fn ($c, $min) => $c->filter(fn ($p) => $p->locations->count() >= (int) $min))
            ->sortBy([['total', 'desc'], [fn ($p) => mb_strtolower($p->player->username), 'asc']])
            ->values();

        return view('players.index', ['players' => $players, 'me' => $me]);
    }

    public function show(Player $player)
    {
        $positions = $this->positions();
        $me = Player::me();
        $rows = collect($positions[$player->id] ?? [])->map(function ($p) use ($me) {
            $p->mine = $me ? $p->board->rowFor($me) : null;

            return $p;
        })->sortByDesc(fn ($p) => $p->row->parcels)->values();

        // Locations they used to be in but no longer appear in the latest snapshot.
        $past = Location::whereHas('snapshots.entries', fn ($q) => $q->where('player_id', $player->id))
            ->whereNotIn('id', $rows->pluck('location.id'))
            ->orderBy('name')
            ->get();

        return view('players.show', compact('player', 'rows', 'past', 'me'));
    }

    public function update(Request $request, Player $player)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:255', Rule::unique('players')->ignore($player->id)],
            'notes' => ['nullable', 'string'],
        ]);
        $player->update($data);

        return back()->with('status', 'Player updated.');
    }

    public function markMe(Player $player)
    {
        Player::where('is_me', true)->update(['is_me' => false]);
        $player->update(['is_me' => true]);

        return back()->with('status', "{$player->username} is now marked as you.");
    }

    public function destroy(Player $player)
    {
        $player->delete();

        return redirect()->route('players.index')->with('status', "Deleted {$player->username} from every snapshot.");
    }
}
