<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Snapshot;
use App\Services\SnapshotWriter;
use Illuminate\Http\Request;
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

        if (! SnapshotWriter::hasEntries($lines)) {
            throw ValidationException::withMessages(['rows' => 'Add at least one player with parcels.']);
        }

        SnapshotWriter::save($snapshot, $location, $data['recorded_on'], $data['notes'] ?? null, $lines);

        return $snapshot;
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
