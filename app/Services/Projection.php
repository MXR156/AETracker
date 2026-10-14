<?php

namespace App\Services;

use App\Models\Holding;
use App\Models\Location;
use App\Models\Player;
use Illuminate\Support\Carbon;

/**
 * Projects my account forward month by month: Atlas Bucks come in from the reward sources I'm
 * part of, every whole parcel's worth is spent on parcels (with the usual rarity odds), and rent
 * is recalculated each month - boost tiers, badges and Super Boost days included.
 */
class Projection
{
    public const DAYS_PER_MONTH = 365 / 12;

    public array $settings;

    public function __construct(public EarningsCalculator $calc)
    {
        $this->settings = $calc->settings;
    }

    private function num(string $key): float
    {
        return max(0, (float) ($this->settings[$key] ?? 0));
    }

    public function explorerClub(): bool
    {
        return (bool) ($this->settings['explorer_club'] ?? false);
    }

    public function premiumLadder(): bool
    {
        return (bool) ($this->settings['premium_ladder'] ?? false);
    }

    /**
     * Where the AB comes from, each with its amount per 30 and 90 days, per month and per year.
     *
     * @return array<int, array{key: string, label: string, given: string, on: bool, per_day: float, per_month: float, per_30: float, per_90: float, per_year: float}>
     */
    public function sources(): array
    {
        $sources = [
            ['key' => 'ab_free_daily_90', 'label' => 'Free daily rewards', 'days' => 90, 'on' => true],
            ['key' => 'ab_free_ladder_30', 'label' => 'Free reward ladder', 'days' => 30, 'on' => true],
            ['key' => 'ab_explorer_90', 'label' => 'Explorer Club', 'days' => 90, 'on' => $this->explorerClub()],
            ['key' => 'ab_premium_30', 'label' => 'Premium Rewards Ladder', 'days' => 30, 'on' => $this->premiumLadder()],
            ['key' => 'ab_extra_month', 'label' => 'Other (Arcade, spins, commissions…)', 'days' => self::DAYS_PER_MONTH, 'on' => true],
        ];

        return array_map(function ($s) {
            $perDay = $this->num($s['key']) / $s['days'];

            return $s + [
                'amount' => $this->num($s['key']),
                'per_day' => $perDay,
                'per_30' => $perDay * 30,
                'per_90' => $perDay * 90,
                'per_month' => $perDay * self::DAYS_PER_MONTH,
                'per_year' => $perDay * 365,
            ];
        }, $sources);
    }

    public function abPerDay(): float
    {
        return array_sum(array_map(fn ($s) => $s['on'] ? $s['per_day'] : 0, $this->sources()));
    }

    public function abPerParcel(): float
    {
        return max(1, $this->num('atlas_bucks_per_parcel'));
    }

    /** Subscriptions I pay for, per month. */
    public function subscriptions(): array
    {
        $subs = [];
        if ($this->explorerClub()) {
            $subs['Explorer Club'] = $this->num('explorer_cost_month');
        }
        if ($this->premiumLadder()) {
            $subs['Premium Rewards Ladder'] = $this->num('premium_cost_month');
        }

        return $subs;
    }

    public function subscriptionCostPerMonth(): float
    {
        return array_sum($this->subscriptions());
    }

    /** Monthly rent targets, e.g. "10, 20, 30, 50". */
    public function rentTargets(): array
    {
        $targets = array_filter(array_map('floatval', preg_split('/[\s,;]+/', (string) ($this->settings['rent_milestones'] ?? ''))), fn ($t) => $t > 0);
        sort($targets);

        return array_values(array_unique($targets));
    }

    /**
     * Run the projection.
     *
     * Month 0 is now. Each month: the month's AB is added to the balance, whole parcels are bought
     * (split across rarities by the odds), then the month's rent is worked out on the new total.
     */
    public function run(?Holding $holding = null, ?int $months = null): array
    {
        $months = max(1, min(120, $months ?? (int) ($this->settings['projection_months'] ?? 24)));
        $holding ??= Holding::current();
        $counts = $holding ? array_map('floatval', $holding->counts()) : array_fill_keys(EarningsCalculator::RARITIES, 0.0);
        $badges = $this->calc->badgesOwned();
        $mix = $this->calc->expectedNewParcelMix();
        $abPerMonth = $this->abPerDay() * self::DAYS_PER_MONTH;
        $abPerParcel = $this->abPerParcel();
        $subsPerMonth = $this->subscriptionCostPerMonth();

        $balance = $this->num('ab_balance');
        $bought = 0;
        $rentTotal = 0.0;
        $subsTotal = 0.0;
        $start = Carbon::now()->startOfMonth();

        $now = $this->calc->calculate($counts, $badges);
        $rows = [[
            'month' => 0,
            'date' => Carbon::now(),
            'parcels' => $now['parcels'],
            'bought' => 0,
            'bought_total' => 0,
            'ab_balance' => $balance,
            'boost' => $now['boost_multiplier'],
            'rent_month' => $now['per_month'],
            'rent_total' => 0.0,
            'subs_total' => 0.0,
        ]];

        for ($m = 1; $m <= $months; $m++) {
            $balance += $abPerMonth;
            $buy = (int) floor($balance / $abPerParcel + 1e-9);
            $balance -= $buy * $abPerParcel;
            $bought += $buy;
            foreach ($mix as $rarity => $share) {
                $counts[$rarity] = ($counts[$rarity] ?? 0) + $buy * $share;
            }

            $r = $this->calc->calculate($counts, $badges);
            $rentTotal += $r['per_month'];
            $subsTotal += $subsPerMonth;
            $rows[] = [
                'month' => $m,
                'date' => $start->copy()->addMonths($m),
                'parcels' => (int) round(array_sum($counts)),
                'bought' => $buy,
                'bought_total' => $bought,
                'ab_balance' => $balance,
                'boost' => $r['boost_multiplier'],
                'rent_month' => $r['per_month'],
                'rent_total' => $rentTotal,
                'subs_total' => $subsTotal,
            ];
        }

        return [
            'rows' => $rows,
            'ab_per_month' => $abPerMonth,
            'parcels_per_month' => $abPerMonth / $abPerParcel,
            'milestones' => $this->milestones($rows),
        ];
    }

    /** The first month each target is reached, or null if not within the projection. */
    private function milestones(array $rows): array
    {
        $first = fn (callable $test) => collect($rows)->first($test);
        $milestones = [];

        foreach ($this->subscriptions() as $name => $cost) {
            if ($cost > 0) {
                $milestones[] = ['label' => "Rent covers {$name}", 'target' => $cost, 'row' => $first(fn ($r) => $r['rent_month'] >= $cost)];
            }
        }
        $subs = $this->subscriptionCostPerMonth();
        if (count(array_filter($this->subscriptions())) > 1) {
            $milestones[] = ['label' => 'Rent covers both subscriptions', 'target' => $subs, 'row' => $first(fn ($r) => $r['rent_month'] >= $subs)];
        }
        if ($subs > 0) {
            $milestones[] = ['label' => 'Total rent earned has paid back the subscriptions', 'target' => null,
                'row' => $first(fn ($r) => $r['month'] > 0 && $r['rent_total'] >= $r['subs_total'])];
        }
        foreach ($this->rentTargets() as $target) {
            $milestones[] = ['label' => 'Rent reaches '.ae_money($target, 2).' a month', 'target' => $target, 'row' => $first(fn ($r) => $r['rent_month'] >= $target)];
        }

        return $milestones;
    }

    /**
     * For each location where I'm on the board but not leading: how long until I could take over
     * if every new parcel went there - with the leader standing still, and growing at their
     * snapshot rate.
     */
    public function takeovers(array $rows): array
    {
        $me = Player::me();
        if (! $me) {
            return [];
        }

        $result = [];
        foreach (Location::orderBy('name')->get() as $location) {
            $board = new Leaderboard($location);
            $mine = $board->rowFor($me);
            if (! $mine || ($mine->rank === 1 && $board->leaders()->count() === 1)) {
                continue;
            }
            $leader = $board->rows->first(fn ($r) => $r->player->id !== $me->id);
            $gap = $board->parcelsToLead($me);
            $growth = $board->growthPerMonth($leader->player);
            $since = $board->latest->recorded_on;
            $monthsSince = $since->diffInDays(Carbon::now()) / self::DAYS_PER_MONTH;

            $still = collect($rows)->first(fn ($r) => $r['bought_total'] >= $gap);
            $growing = $growth === null ? null : collect($rows)->first(
                fn ($r) => $r['bought_total'] >= $gap + max(0, $growth) * ($r['month'] + $monthsSince)
            );

            $result[] = (object) [
                'location' => $location,
                'mine' => $mine,
                'leader' => $leader,
                'gap' => $gap,
                'growth' => $growth,
                'still' => $still,
                'growing' => $growing,
            ];
        }

        usort($result, fn ($a, $b) => $a->gap <=> $b->gap);

        return $result;
    }
}
