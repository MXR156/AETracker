<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Player;
use App\Services\Leaderboard;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LocationController extends Controller
{
    public function index(Request $request)
    {
        $me = Player::me();
        $query = Location::query()->orderBy('name');
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($q = trim((string) $request->query('q'))) {
            $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('region', 'like', "%{$q}%"));
        }

        $boards = $query->get()->map(fn ($l) => new Leaderboard($l));

        if ($request->query('filter') === 'mine' && $me) {
            $boards = $boards->filter(fn ($b) => $b->rowFor($me));
        } elseif ($request->query('filter') === 'mayor' && $me) {
            $boards = $boards->filter(fn ($b) => $b->leaders()->contains(fn ($r) => $r->player->id === $me->id));
        } elseif ($request->query('filter') === 'not-mayor' && $me) {
            $boards = $boards->filter(fn ($b) => $b->leader() && ! $b->leaders()->contains(fn ($r) => $r->player->id === $me->id));
        }

        return view('locations.index', ['boards' => $boards, 'me' => $me]);
    }

    public function create()
    {
        return view('locations.form', ['location' => new Location(['type' => 'village'])]);
    }

    public function store(Request $request)
    {
        $location = Location::create($this->validated($request));

        return redirect()->route('locations.snapshots.create', $location)
            ->with('status', "Added {$location->name}. Now add its first snapshot.");
    }

    public function show(Location $location)
    {
        $board = new Leaderboard($location);
        $me = Player::me();

        return view('locations.show', [
            'location' => $location,
            'board' => $board,
            'me' => $me,
            'chart' => $board->chartData((int) request('top', 10)),
        ]);
    }

    public function edit(Location $location)
    {
        return view('locations.form', ['location' => $location]);
    }

    public function update(Request $request, Location $location)
    {
        $location->update($this->validated($request, $location));

        return redirect()->route('locations.show', $location)->with('status', 'Location updated.');
    }

    public function destroy(Location $location)
    {
        $location->delete();

        return redirect()->route('locations.index')->with('status', "Deleted {$location->name} and all its snapshots.");
    }

    private function validated(Request $request, ?Location $location = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(config('aetracker.location_types')))],
            'region' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $duplicate = Location::where('name', $data['name'])->where('type', $data['type'])
            ->where(fn ($q) => filled($data['region'] ?? null) ? $q->where('region', $data['region']) : $q->whereNull('region'))
            ->when($location, fn ($q) => $q->whereKeyNot($location->id))
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['name' => 'That location already exists.']);
        }

        return $data;
    }
}
