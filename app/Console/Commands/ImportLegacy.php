<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Models\Player;
use App\Models\Snapshot;
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

        $old = new PDO('sqlite:'.$path, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

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

            foreach ($rows as $r) {
                $snapshot = Snapshot::firstOrCreate(['location_id' => $map[$r['name']]->id, 'recorded_on' => $r['recorded_on']]);
                $stats['snapshots'] += $snapshot->wasRecentlyCreated ? 1 : 0;
                $entry = $snapshot->entries()->updateOrCreate(
                    ['player_id' => Player::findOrCreateByName($r['username'])->id],
                    ['parcels' => (int) $r['parcel_count']],
                );
                $stats['entries'] += $entry->wasRecentlyCreated ? 1 : 0;
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
