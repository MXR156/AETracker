<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Models\Player;
use App\Models\Snapshot;
use App\Services\SnapshotWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;

/**
 * Imports data from the original tracker's SQLite database
 * (locations, players, location_players, parcel_snapshots).
 */
class ImportLegacy extends Command
{
    protected $signature = 'aetracker:import-legacy
        {path : Path to the old database.sqlite file}
        {--me= : Username to mark as you, e.g. SuperSparrow}';

    protected $description = 'Import locations, players and snapshots from the original tracker\'s SQLite database';

    public function handle(): int
    {
        $path = $this->argument('path');
        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        // Read-only: the old database is never changed (and may not be writable, e.g. copied into a Docker volume).
        $old = new PDO('sqlite:'.$path, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY,
        ]);

        $rows = $old->query('
            SELECT l.name, l.type, p.username, date(s.recorded_on) AS recorded_on, s.parcel_count
            FROM parcel_snapshots s
            JOIN location_players lp ON lp.id = s.location_player_id
            JOIN locations l ON l.id = lp.location_id
            JOIN players p ON p.id = lp.player_id
            ORDER BY l.name, s.recorded_on
        ')->fetchAll();
        $locations = $old->query('SELECT name, type FROM locations')->fetchAll();

        $stats = ['locations' => 0, 'snapshots' => 0, 'entries' => 0];

        DB::transaction(function () use ($rows, $locations, &$stats) {
            $map = [];
            foreach ($locations as $l) {
                $location = Location::firstOrCreate(
                    ['name' => $l['name'], 'type' => $this->type($l['type']), 'region' => null],
                );
                $stats['locations'] += $location->wasRecentlyCreated ? 1 : 0;
                $map[$l['name']] = $location;
            }

            // Group into one snapshot per location per date, so several "Anonymous" players stay separate.
            $groups = [];
            foreach ($rows as $r) {
                $groups[$r['name']."\0".$r['recorded_on']][] = $r;
            }

            foreach ($groups as $group) {
                $location = $map[$group[0]['name']];
                $snapshot = Snapshot::firstOrCreate(['location_id' => $location->id, 'recorded_on' => $group[0]['recorded_on']]);
                $stats['snapshots'] += $snapshot->wasRecentlyCreated ? 1 : 0;

                $players = SnapshotWriter::resolve(
                    $location,
                    array_map(fn ($r) => [$r['username'], (int) $r['parcel_count']], $group),
                );

                foreach ($players as [$player, $parcels]) {
                    $entry = $snapshot->entries()->updateOrCreate(['player_id' => $player->id], ['parcels' => $parcels]);
                    $stats['entries'] += $entry->wasRecentlyCreated ? 1 : 0;
                }
            }
        });

        if ($me = $this->option('me')) {
            Player::where('is_me', true)->update(['is_me' => false]);
            Player::findOrCreateByName($me)->update(['is_me' => true]);
            $this->info("Marked {$me} as you.");
        }

        $this->info("Imported {$stats['locations']} new locations, {$stats['snapshots']} new snapshots and {$stats['entries']} new entries.");

        return self::SUCCESS;
    }

    private function type(?string $type): string
    {
        $type = strtolower(trim((string) $type));

        return array_key_exists($type, config('aetracker.location_types')) ? $type : 'village';
    }
}
