<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Snapshot;
use App\Services\AtlasImport;
use App\Services\SnapshotWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Import a leaderboard straight from the Atlas Earth web map via a bookmarklet.
 *
 * The bookmarklet POSTs the leaderboard to receive() (which has no CSRF token, so it only
 * stages the data), and you confirm it on the review page before anything is saved.
 */
class ImportController extends Controller
{
    private const CACHE_PREFIX = 'atlas-import:';

    public function index()
    {
        return view('import.index', ['bookmarklet' => $this->bookmarklet()]);
    }

    /** The bookmarklet as a javascript: URL pointing at this tracker. */
    private function bookmarklet(): string
    {
        $code = file_get_contents(resource_path('bookmarklet/atlas-earth.js'));
        $code = preg_replace('~^\s*//.*$~m', '', $code);              // drop comment lines
        $code = preg_replace('~\s*\n\s*~', "\n", trim($code));        // and indentation
        $code = str_replace('__TARGET__', route('import.receive'), $code);

        return 'javascript:'.rawurlencode($code);
    }

    public function receive(Request $request)
    {
        $payload = (string) $request->input('payload');
        if (strlen($payload) > 500_000) {
            return redirect()->route('import.index')->withErrors(['payload' => 'That leaderboard is too big to import.']);
        }

        try {
            $import = AtlasImport::fromPayload($payload);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('import.index')->withInput()->withErrors(['payload' => $e->getMessage()]);
        }

        $token = Str::random(32);
        Cache::put(self::CACHE_PREFIX.$token, $import->toArray(), now()->addDay());

        return redirect()->route('import.review', $token);
    }

    public function review(Request $request, string $token)
    {
        $import = $this->load($token);

        $choice = $request->query('location');
        $location = match (true) {
            $choice === 'new' => null,
            filled($choice) => Location::find($choice),
            default => $import->matchLocation(),
        };

        $date = now()->toDateString();
        $rows = [];
        $gone = collect();
        $latest = null;
        $existing = null;

        if ($location) {
            $latest = $location->snapshots()->with('entries.player')->get()->last();
            $existing = $location->snapshots()->where('recorded_on', $date)->first();
            // Compare with the latest snapshot before today (today's will be replaced).
            $compare = $latest && $latest->recorded_on->toDateString() === $date ? $latest->previous()?->load('entries.player') : $latest;
            $before = $compare ? $compare->entries->keyBy('player_id') : collect();

            $resolved = SnapshotWriter::resolve($location, $import->lines(), create: false);
            $seen = [];
            foreach ($resolved as [$player, $parcels, $name]) {
                $prev = $player ? $before->get($player->id) : null;
                $seen[] = $player?->id;
                $rows[] = (object) ['name' => $name, 'parcels' => $parcels, 'is_new' => ! $prev, 'is_me' => (bool) $player?->is_me,
                    'change' => $prev ? $parcels - $prev->parcels : null, 'known' => (bool) $player];
            }
            $gone = $before->reject(fn ($e) => in_array($e->player_id, $seen))->values();
            $latest = $compare;
        } else {
            foreach (SnapshotWriter::resolve(new Location, $import->lines(), create: false) as [$player, $parcels, $name]) {
                $rows[] = (object) ['name' => $name, 'parcels' => $parcels, 'is_new' => true, 'is_me' => false, 'change' => null, 'known' => (bool) $player];
            }
        }

        return view('import.review', [
            'token' => $token,
            'import' => $import,
            'location' => $location,
            'matched' => $import->matchLocation(),
            'locations' => Location::orderBy('name')->get(),
            'rows' => $rows,
            'gone' => $gone,
            'previous' => $latest,
            'existing' => $existing,
            'date' => $date,
        ]);
    }

    public function save(Request $request, string $token)
    {
        $import = $this->load($token);
        $data = $request->validate([
            'location' => ['required'],
            'recorded_on' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'name' => ['required_if:location,new', 'nullable', 'string', 'max:255'],
            'type' => ['required_if:location,new', 'nullable', Rule::in(array_keys(config('aetracker.location_types')))],
            'region' => ['nullable', 'string', 'max:255'],
        ]);

        if ($data['location'] === 'new') {
            $location = Location::create([
                'name' => $data['name'],
                'type' => $data['type'],
                'region' => $data['region'] ?? null,
                'atlas_key' => Location::where('atlas_key', $import->key)->exists() ? null : $import->key,
            ]);
        } else {
            $location = Location::findOrFail($data['location']);
            // Remember the link so the next import of this leaderboard finds it automatically.
            if (! $location->atlas_key && ! Location::where('atlas_key', $import->key)->exists()) {
                $location->update(['atlas_key' => $import->key]);
            }
        }

        $snapshot = Snapshot::where('location_id', $location->id)->where('recorded_on', $data['recorded_on'])->first()
            ?? new Snapshot;
        SnapshotWriter::save($snapshot, $location, $data['recorded_on'], $data['notes'] ?? null, $import->lines());
        Cache::forget(self::CACHE_PREFIX.$token);

        return redirect()->route('locations.show', $location)
            ->with('status', 'Imported '.count($import->rows).' players into '.$location->name.' for '.$snapshot->recorded_on->format('j M Y').'.');
    }

    private function load(string $token): AtlasImport
    {
        $data = Cache::get(self::CACHE_PREFIX.$token);
        abort_unless($data, 404, 'This import has expired or was already saved. Click the bookmarklet again.');

        return AtlasImport::fromArray($data);
    }
}
