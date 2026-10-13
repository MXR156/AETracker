<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Player;
use App\Models\Snapshot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SnapshotController extends Controller
{
    public function create(Location $location)
    {
        // Start from the latest snapshot so you only need to change the numbers that moved.
        $latest = $location->snapshots()->with('entries.player')->get()->last();
        $rows = $latest
            ? $latest->entries->map(fn ($e) => ['username' => $e->player->username, 'parcels' => $e->parcels])->all()
            : [];

        return view('snapshots.form', [
            'location' => $location,
            'snapshot' => new Snapshot(['recorded_on' => now()]),
            'rows' => $rows,
            'previous' => $latest,
        ]);
    }

    public function store(Request $request, Location $location)
    {
        $snapshot = $this->save($request, $location, new Snapshot(['location_id' => $location->id]));

        return redirect()->route('locations.show', $location)
            ->with('status', 'Snapshot for '.$snapshot->recorded_on->format('j M Y').' saved.');
    }

    public function edit(Snapshot $snapshot)
    {
        $snapshot->load('entries.player', 'location');

        return view('snapshots.form', [
            'location' => $snapshot->location,
            'snapshot' => $snapshot,
            'rows' => $snapshot->entries->map(fn ($e) => ['username' => $e->player->username, 'parcels' => $e->parcels])->all(),
            'previous' => $snapshot->previous(),
        ]);
    }

    public function update(Request $request, Snapshot $snapshot)
    {
        $this->save($request, $snapshot->location, $snapshot);

        return redirect()->route('locations.show', $snapshot->location_id)->with('status', 'Snapshot updated.');
    }

    public function destroy(Snapshot $snapshot)
    {
        $snapshot->delete();

        return redirect()->route('locations.show', $snapshot->location_id)->with('status', 'Snapshot deleted.');
    }

    private function save(Request $request, Location $location, Snapshot $snapshot): Snapshot
    {
        $data = $request->validate([
            'recorded_on' => [
                'required', 'date',
                Rule::unique('snapshots')->where('location_id', $location->id)->ignore($snapshot->id),
            ],
            'notes' => ['nullable', 'string'],
            'rows' => ['array'],
            'rows.*.username' => ['nullable', 'string', 'max:255'],
            'rows.*.parcels' => ['nullable', 'integer', 'min:0'],
            'paste' => ['nullable', 'string'],
        ], [
            'recorded_on.unique' => 'There is already a snapshot for this location on that date - edit that one instead.',
        ]);

        $lines = [];
        foreach ($data['rows'] ?? [] as $row) {
            $name = trim((string) ($row['username'] ?? ''));
            if ($name !== '' && ($row['parcels'] ?? null) !== null) {
                $lines[] = [$name, (int) $row['parcels']];
            }
        }
        // Pasted lines come after the table rows, so they win for the same player.
        $lines = array_merge($lines, self::parsePaste($data['paste'] ?? ''));

        [$counts, $ghosts] = self::splitEntries($lines);

        if (! $counts && ! $ghosts) {
            throw ValidationException::withMessages(['rows' => 'Add at least one player with parcels.']);
        }

        DB::transaction(function () use ($snapshot, $location, $data, $counts, $ghosts) {
            $snapshot->fill(['recorded_on' => $data['recorded_on'], 'notes' => $data['notes'] ?? null])->save();
            $snapshot->entries()->delete();
            foreach ($counts as [$name, $parcels]) {
                $snapshot->entries()->create(['player_id' => Player::findOrCreateByName($name)->id, 'parcels' => $parcels]);
            }
            foreach ($ghosts as $number => $parcels) {
                $snapshot->entries()->create(['player_id' => Player::ghost($location, $number)->id, 'parcels' => $parcels]);
            }
        });

        return $snapshot;
    }

    /**
     * Split [name, parcels] lines into named players and anonymous "ghost" players.
     *
     * Named players are matched case-insensitively (a later line wins). Every "Anonymous" line is a
     * separate player: "Anonymous #2" keeps its number, and plain "Anonymous" lines get the lowest free
     * numbers, biggest holder first - so a location's ghosts keep roughly the same numbers over time.
     * Anyone on 0 parcels has left the board and is dropped.
     *
     * @param  array<int, array{0: string, 1: int}>  $lines
     * @return array{0: array<string, array{0: string, 1: int}>, 1: array<int, int>}  [named, ghost number => parcels]
     */
    public static function splitEntries(array $lines): array
    {
        $named = [];
        $ghosts = [];
        $unnumbered = [];
        foreach ($lines as [$name, $parcels]) {
            if (! Player::isGhostName($name)) {
                $named[mb_strtolower($name)] = [$name, $parcels];
            } elseif (($number = Player::ghostNumber($name)) && $number > 0) {
                $ghosts[$number] = $parcels;
            } else {
                $unnumbered[] = $parcels;
            }
        }

        $ghosts = array_filter($ghosts, fn ($p) => $p > 0);
        $unnumbered = array_filter($unnumbered, fn ($p) => $p > 0);
        rsort($unnumbered);
        $next = 1;
        foreach ($unnumbered as $parcels) {
            while (isset($ghosts[$next])) {
                $next++;
            }
            $ghosts[$next] = $parcels;
        }
        ksort($ghosts);

        return [array_filter($named, fn ($c) => $c[1] > 0), $ghosts];
    }

    /**
     * Turn pasted text into [username, parcels] pairs. One player per line, with the parcel
     * count as the last number on the line, e.g. "SuperSparrow 100", "1. SuperSparrow - 100",
     * "SuperSparrow,100" or "#2 LogisticHawk 82 parcels".
     *
     * @return array<int, array{0: string, 1: int}>
     */
    public static function parsePaste(string $text): array
    {
        $result = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $line = trim(preg_replace('/\bparcels?\b/i', '', $line));
            if (! preg_match('/^(.*?)[\s,;:=\-\t|]+([\d,]+)\s*$/u', $line, $m)) {
                continue;
            }
            $name = trim($m[1], " \t,;:-|");
            // Drop a leading rank like "1.", "#1", "1)" when a name follows it.
            $name = preg_replace('/^#?\d+[.)]\s*|^#\d+\s+/', '', $name);
            $name = trim($name);
            if ($name !== '') {
                $result[] = [$name, (int) str_replace(',', '', $m[2])];
            }
        }

        return $result;
    }
}
