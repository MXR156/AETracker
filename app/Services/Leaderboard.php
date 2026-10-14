<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Player;
use App\Models\Snapshot;
use Illuminate\Support\Collection;

/**
 * Builds the leaderboard for a location from its snapshots: who is leader (mayor),
 * everyone's rank, and how much each player gained since the previous and the first snapshot.
 */
class Leaderboard
{
    /** @var Collection<int, Snapshot> */
    public Collection $snapshots;

    public ?Snapshot $latest;

    public ?Snapshot $previous;

    /** @var Collection<int, object> */
    public Collection $rows;

    public function __construct(public Location $location)
    {
        $this->snapshots = $location->snapshots()->with('entries.player')->get();
        $this->latest = $this->snapshots->last();
        $this->previous = $this->snapshots->count() > 1 ? $this->snapshots[$this->snapshots->count() - 2] : null;
        $this->rows = $this->buildRows();
    }

    private function buildRows(): Collection
    {
        if (! $this->latest) {
            return collect();
        }

        $previous = $this->previous?->entries->keyBy('player_id') ?? collect();

        // Each player's first ever recorded count in this location.
        $first = [];
        foreach ($this->snapshots as $snapshot) {
            foreach ($snapshot->entries as $entry) {
                $first[$entry->player_id] ??= ['parcels' => $entry->parcels, 'on' => $snapshot->recorded_on];
            }
        }

        $total = max(1, $this->latest->entries->sum('parcels'));
        $rank = 0;
        $lastParcels = null;

        return $this->latest->entries
            ->sortBy([['parcels', 'desc'], [fn ($e) => mb_strtolower($e->player->username), 'asc']])
            ->values()
            ->map(function ($entry, $i) use ($previous, $first, $total, &$rank, &$lastParcels) {
                // Players on the same count share a rank.
                if ($entry->parcels !== $lastParcels) {
                    $rank = $i + 1;
                    $lastParcels = $entry->parcels;
                }
                $prev = $previous->get($entry->player_id);

                return (object) [
                    'rank' => $rank,
                    'player' => $entry->player,
                    'parcels' => $entry->parcels,
                    'share' => $entry->parcels / $total * 100,
                    'change' => $this->previous ? $entry->parcels - ($prev?->parcels ?? 0) : null,
                    'is_new' => $this->previous && ! $prev,
                    'since_first' => $entry->parcels - $first[$entry->player_id]['parcels'],
                    'first_seen' => $first[$entry->player_id]['on'],
                ];
            });
    }

    /** Rows sharing the top spot (more than one means a tie). */
    public function leaders(): Collection
    {
        return $this->rows->where('rank', 1)->values();
    }

    public function leader(): ?object
    {
        return $this->rows->first();
    }

    public function rowFor(?Player $player): ?object
    {
        return $player ? $this->rows->first(fn ($r) => $r->player->id === $player->id) : null;
    }

    /** Parcels a player would need to add to take the top spot outright (0 if they hold it alone). */
    public function parcelsToLead(?Player $player): ?int
    {
        if (! $player || ! $this->leader()) {
            return null;
        }
        $mine = $this->rowFor($player)?->parcels ?? 0;
        $bestOther = $this->rows->first(fn ($r) => $r->player->id !== $player->id)?->parcels ?? 0;

        return max(0, $bestOther - $mine + 1);
    }

    /**
     * How fast a player has been growing here, in parcels per month, from their first to their
     * latest snapshot. Null when there's only one snapshot of them to go on.
     */
    public function growthPerMonth(Player $player): ?float
    {
        $seen = $this->snapshots
            ->map(fn ($s) => [$s->recorded_on, $s->entries->firstWhere('player_id', $player->id)?->parcels])
            ->filter(fn ($p) => $p[1] !== null)
            ->values();
        if ($seen->count() < 2) {
            return null;
        }
        $days = $seen->first()[0]->diffInDays($seen->last()[0]);

        return $days > 0 ? ($seen->last()[1] - $seen->first()[1]) / $days * (365 / 12) : null;
    }

    /** Players that left the board since the previous snapshot. */
    public function dropped(): Collection
    {
        if (! $this->previous) {
            return collect();
        }
        $current = $this->latest->entries->pluck('player_id')->all();

        return $this->previous->entries->reject(fn ($e) => in_array($e->player_id, $current))->values();
    }

    /**
     * Chart.js data: one line per player (top $limit in the latest snapshot plus me), x = snapshot dates.
     */
    public function chartData(int $limit = 10): array
    {
        $me = Player::me();
        $players = $this->rows->take($limit)->pluck('player');
        if ($me && ! $players->contains('id', $me->id) && $this->rowFor($me)) {
            $players->push($me);
        }

        return [
            'labels' => $this->snapshots->map(fn ($s) => $s->recorded_on->format('Y-m-d'))->all(),
            'datasets' => $players->map(fn ($p) => [
                'label' => $p->username,
                'isMe' => $p->is_me,
                'data' => $this->snapshots->map(fn ($s) => $s->entries->firstWhere('player_id', $p->id)?->parcels)->all(),
            ])->values()->all(),
        ];
    }
}
