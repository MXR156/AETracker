<?php

namespace App\Services;

use App\Models\Holding;
use App\Models\Location;
use App\Models\Player;
use Illuminate\Support\Carbon;

/**
 * Projects my account forward day by day: Atlas Bucks come in from the daily login streak, the
 * monthly reward ladders and anything else; every 100 AB buys a parcel (with the usual rarity
 * odds); the ladders' badge and legendary upgrade are applied; and rent is recalculated - boost
 * tiers, badges and Super Boost days included. Results are reported at the end of each month.
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

    public function abPerParcel(): float
    {
        return max(1, $this->num('atlas_bucks_per_parcel'));
    }

    // ---- Daily login streak -------------------------------------------------------------------

    public function cycleDays(): int
    {
        return max(1, (int) $this->num('login_cycle_days'));
    }

    /** AB for each day of the login cycle: [1 => ab, 2 => ab, ...]. */
    public function loginSchedule(string $which): array
    {
        $base = $this->num("login_{$which}_base");
        $days = array_fill(1, $this->cycleDays(), $base);
        foreach (EarningsCalculator::parseTiers($this->settings["login_{$which}_bonus"] ?? '') as $bonus) {
            $day = (int) $bonus['from'];
            if ($day >= 1 && $day <= $this->cycleDays()) {
                $days[$day] = $bonus['value'];
            }
        }

        return $days;
    }

    /** Which day of the streak a date falls on, counting on from the day I said I was on. */
    public function streakDay(?Carbon $date = null): int
    {
        $date ??= Carbon::today();
        $setOn = filled($this->settings['login_streak_set_on'] ?? null) ? Carbon::parse($this->settings['login_streak_set_on']) : Carbon::today();
        $day = max(1, (int) $this->num('login_streak_day'));
        $elapsed = (int) $setOn->startOfDay()->diffInDays($date->copy()->startOfDay(), false);

        return (($day - 1 + $elapsed) % $this->cycleDays() + $this->cycleDays()) % $this->cycleDays() + 1;
    }

    // ---- Monthly reward ladders ---------------------------------------------------------------

    /**
     * The steps of a ladder: "5, 15 bid tokens, ..., 1 badge" ->
     * [['ab' => 5, 'badges' => 0, 'legendary' => 0, 'other' => null], ['ab' => 0, ..., 'other' => '15 bid tokens'], ...]
     */
    public function ladder(string $which): array
    {
        $steps = [];
        foreach (array_filter(array_map('trim', explode(',', (string) ($this->settings["ladder_{$which}"] ?? ''))), 'strlen') as $item) {
            $count = preg_match('/^(\d+(?:\.\d+)?)/', $item, $m) ? (float) $m[1] : 1;
            $step = ['ab' => 0, 'badges' => 0, 'legendary' => 0, 'other' => null, 'label' => $item];
            if (is_numeric($item)) {
                $step['ab'] = (float) $item;
            } elseif (stripos($item, 'badge') !== false) {
                $step['badges'] = (int) $count;
            } elseif (stripos($item, 'legendary') !== false) {
                $step['legendary'] = (int) $count;
            } else {
                $step['other'] = $item;
            }
            $steps[] = $step;
        }

        return $steps;
    }

    // ---- Totals -------------------------------------------------------------------------------

    /**
     * Where the AB comes from, with amounts per 30 and 90 days and per year, plus what else each gives.
     */
    public function sources(): array
    {
        $cycle = $this->cycleDays();
        $login = fn ($which) => array_sum($this->loginSchedule($which)) / $cycle; // average per day
        $ladder = fn ($which) => array_sum(array_column($this->ladder($which), 'ab')); // per month
        $extras = fn ($which) => implode(', ', array_column(array_filter($this->ladder($which), fn ($s) => $s['ab'] == 0), 'label'));

        // Logins are counted per full streak cycle, ladders per month: 90 days = 1 cycle (when the
        // cycle is 90 days) + 3 months of ladders.
        $sources = [
            ['label' => "Daily login ({$cycle}-day streak)", 'on' => true, 'per_90' => $login('free') * 90, 'also' => null],
            ['label' => 'Free reward ladder', 'on' => true, 'per_90' => $ladder('free') * 3, 'also' => $extras('free')],
            ['label' => 'Explorer Club login rewards', 'on' => $this->explorerClub(), 'per_90' => $login('explorer') * 90, 'also' => null],
            ['label' => 'Premium Rewards Ladder', 'on' => $this->premiumLadder(), 'per_90' => $ladder('premium') * 3, 'also' => $extras('premium')],
            ['label' => 'Other (Arcade, spins, commissions…)', 'on' => true, 'per_90' => $this->num('ab_extra_month') * 3, 'also' => null],
        ];

        return array_map(fn ($s) => $s + ['per_30' => $s['per_90'] / 3, 'per_year' => $s['per_90'] / 90 * 365], $sources);
    }

    public function abPer90Days(): float
    {
        return array_sum(array_map(fn ($s) => $s['on'] ? $s['per_90'] : 0, $this->sources()));
    }

    public function abPerDay(): float
    {
        return $this->abPer90Days() / 90;
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

    // ---- The projection -----------------------------------------------------------------------

    /**
     * Run the projection day by day from tomorrow (today's rewards are assumed collected), with a
     * row for now and one at the end of each of the next $months calendar months.
     */
    public function run(?Holding $holding = null, ?int $months = null, ?Carbon $today = null): array
    {
        $months = max(1, min(120, $months ?? (int) ($this->settings['projection_months'] ?? 24)));
        $today = ($today ?? Carbon::today())->copy()->startOfDay();
        $holding ??= Holding::current();
        $counts = $holding ? array_map('floatval', $holding->counts()) : array_fill_keys(EarningsCalculator::RARITIES, 0.0);
        $badges = $this->calc->badgesOwned();
        $mix = $this->calc->expectedNewParcelMix();
        $abPerParcel = $this->abPerParcel();
        $subsPerMonth = $this->subscriptionCostPerMonth();

        $loginFree = $this->loginSchedule('free');
        $loginExplorer = $this->explorerClub() ? $this->loginSchedule('explorer') : [];
        $ladders = [$this->ladder('free')];
        if ($this->premiumLadder()) {
            $ladders[] = $this->ladder('premium');
        }
        $extraPerMonth = $this->num('ab_extra_month');

        $balance = $this->num('ab_balance');
        $bought = $boughtThisMonth = 0;
        $upgrades = 0;
        $abTotal = 0.0;
        $rentTotal = 0.0;
        $subsTotal = 0.0;

        $state = $this->calc->calculate($counts, $badges);
        // A snapshot of the running totals (by reference - they change as the days go by).
        $row = function (Carbon $date, int $m) use (&$counts, &$boughtThisMonth, &$bought, &$badges, &$upgrades, &$abTotal, &$balance, &$state, &$rentTotal, &$subsTotal) {
            return [
                'month' => $m,
                'date' => $date->copy(),
                'parcels' => (int) round(array_sum($counts)),
                'bought' => $boughtThisMonth,
                'bought_total' => $bought,
                'badges' => $badges,
                'upgrades' => $upgrades,
                'ab_total' => $abTotal,
                'ab_balance' => $balance,
                'boost' => $state['boost_multiplier'],
                'rent_month' => $state['per_month'],
                'rent_total' => $rentTotal,
                'subs_total' => $subsTotal,
            ];
        };
        $rows = [$row($today, 0)];

        // Month 1 is the rest of this month; each row is at a month's last day.
        $end = $today->copy()->startOfMonth()->addMonths($months - 1)->endOfMonth()->startOfDay();
        $month = 1;
        if ($today->isSameDay($today->copy()->endOfMonth())) {
            $subsTotal += $subsPerMonth;
            $rows[] = $row($today, $month++);
        }
        for ($day = $today->copy()->addDay(); $day->lte($end); $day->addDay()) {
            $changed = false;

            // Atlas Bucks in: login streak, ladder step (days 1-15 of the month), anything else.
            $streak = $this->streakDay($day);
            $ab = ($loginFree[$streak] ?? 0) + ($loginExplorer[$streak] ?? 0) + $extraPerMonth / $day->daysInMonth;
            foreach ($ladders as $ladder) {
                $step = $ladder[$day->day - 1] ?? null;
                if ($step) {
                    $ab += $step['ab'];
                    if ($step['badges']) {
                        $badges += $step['badges'];
                        $changed = true;
                    }
                    for ($i = 0; $i < $step['legendary'] && $counts['common'] >= 1; $i++) {
                        $counts['common']--;
                        $counts['legendary']++;
                        $upgrades++;
                        $changed = true;
                    }
                }
            }
            $abTotal += $ab;
            $balance += $ab;

            // Every 100 AB buys a parcel straight away.
            $buy = (int) floor($balance / $abPerParcel + 1e-9);
            if ($buy > 0) {
                $balance -= $buy * $abPerParcel;
                $bought += $buy;
                $boughtThisMonth += $buy;
                foreach ($mix as $rarity => $share) {
                    $counts[$rarity] = ($counts[$rarity] ?? 0) + $buy * $share;
                }
                $changed = true;
            }

            if ($changed) {
                $state = $this->calc->calculate($counts, $badges);
            }
            $rentTotal += $state['avg_per_day'];

            if ($day->isSameDay($day->copy()->endOfMonth())) {
                $subsTotal += $subsPerMonth;
                $rows[] = $row($day, $month++);
                $boughtThisMonth = 0;
            }
        }

        return [
            'rows' => $rows,
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

            $still = collect($rows)->first(fn ($r) => $r['bought_total'] >= $gap);
            $growing = $growth === null ? null : collect($rows)->first(
                fn ($r) => $r['bought_total'] >= $gap + max(0, $growth) * $since->diffInDays($r['date']) / self::DAYS_PER_MONTH
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
