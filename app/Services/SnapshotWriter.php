<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Player;
use App\Models\Snapshot;
use Illuminate\Support\Facades\DB;

/**
 * Saves a location's leaderboard as a snapshot. Used by the snapshot form, the
 * Atlas Earth browser import and the legacy importer, so they all treat names,
 * anonymous players and zero counts the same way.
 */
class SnapshotWriter
{
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

    /** @param  array<int, array{0: string, 1: int}>  $lines */
    public static function hasEntries(array $lines): bool
    {
        [$named, $ghosts] = self::splitEntries($lines);

        return $named || $ghosts;
    }

    /**
     * Resolve [name, parcels] lines to players for a location: [[Player, parcels], ...].
     * Ghost players are created as needed; named players are only looked up or created when $create is true.
     *
     * @param  array<int, array{0: string, 1: int}>  $lines
     * @return array<int, array{0: Player|null, 1: int, 2: string}>  [player (null if new and not created), parcels, display name]
     */
    public static function resolve(Location $location, array $lines, bool $create = true): array
    {
        [$named, $ghosts] = self::splitEntries($lines);
        $result = [];
        foreach ($named as [$name, $parcels]) {
            $player = $create
                ? Player::findOrCreateByName($name)
                : Player::real()->whereRaw('LOWER(username) = ?', [mb_strtolower(trim($name))])->first();
            $result[] = [$player, $parcels, $player?->username ?? $name];
        }
        foreach ($ghosts as $number => $parcels) {
            $player = $create
                ? Player::ghost($location, $number)
                : Player::where('location_id', $location->id)->where('username', 'Anonymous #'.$number)->first();
            $result[] = [$player, $parcels, 'Anonymous #'.$number];
        }
        usort($result, fn ($a, $b) => $b[1] <=> $a[1]);

        return $result;
    }

    /**
     * Replace the snapshot's entries with these lines.
     *
     * @param  array<int, array{0: string, 1: int}>  $lines
     */
    public static function save(Snapshot $snapshot, Location $location, string $recordedOn, ?string $notes, array $lines): Snapshot
    {
        DB::transaction(function () use ($snapshot, $location, $recordedOn, $notes, $lines) {
            $snapshot->fill(['location_id' => $location->id, 'recorded_on' => $recordedOn, 'notes' => $notes])->save();
            $snapshot->entries()->delete();
            foreach (self::resolve($location, $lines) as [$player, $parcels]) {
                $snapshot->entries()->create(['player_id' => $player->id, 'parcels' => $parcels]);
            }
        });

        return $snapshot;
    }
}
