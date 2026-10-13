<?php

namespace App\Services;

use App\Models\Holding;
use App\Models\Setting;

/**
 * Estimates Atlas Earth rent from parcel counts, badges and ad boost, and compares
 * "what if I bought X" options for the planner.
 */
class EarningsCalculator
{
    public const RARITIES = ['common', 'rare', 'epic', 'legendary'];

    public array $settings;

    public function __construct(?array $settings = null)
    {
        $this->settings = $settings ?? Setting::allValues();
    }

    public function rentPerSecond(string $rarity): float
    {
        return (float) ($this->settings['rent_'.$rarity] ?? 0);
    }

    /** @return array<int, array{from: float, value: float}> sorted ascending by "from" */
    public static function parseTiers(?string $text): array
    {
        $tiers = [];
        foreach (preg_split('/[\r\n,;]+/', (string) $text) as $line) {
            if (preg_match('/^\s*([\d.]+)\s*[:=]\s*([\d.]+)\s*x?%?\s*$/i', $line, $m)) {
                $tiers[] = ['from' => (float) $m[1], 'value' => (float) $m[2]];
            }
        }
        usort($tiers, fn ($a, $b) => $a['from'] <=> $b['from']);

        return $tiers;
    }

    /** The value of the highest tier whose "from" is <= $amount (0 when below every tier). */
    public static function tierValue(array $tiers, float $amount): float
    {
        $value = 0.0;
        foreach ($tiers as $tier) {
            if ($amount + 1e-9 >= $tier['from']) {
                $value = $tier['value'];
            }
        }

        return $value;
    }

    public function boostTiers(): array
    {
        return self::parseTiers($this->settings['boost_tiers'] ?? '');
    }

    public function badgeTiers(): array
    {
        return self::parseTiers($this->settings['badge_tiers'] ?? '');
    }

    /** Badge rent boost (%) for owning $count badges. */
    public function badgePercent(int $count): float
    {
        return self::tierValue($this->badgeTiers(), $count);
    }

    /** How many badges I own (entered on My Parcels). */
    public function badgesOwned(): int
    {
        return max(0, (int) ($this->settings['badges_owned'] ?? 0));
    }

    public function badgeCost(): float
    {
        return max(0, (float) ($this->settings['cost_per_badge'] ?? 0));
    }

    /**
     * Full earnings breakdown.
     *
     * @param  array<string,int|float>  $counts  parcels per rarity
     */
    public function calculate(array $counts, int $badgeCount, ?float $boostHours = null): array
    {
        $boostHours = max(0, min(24, $boostHours ?? (float) $this->settings['boost_hours_per_day']));

        $base = 0.0;
        foreach (self::RARITIES as $rarity) {
            $base += ($counts[$rarity] ?? 0) * $this->rentPerSecond($rarity);
        }

        $commonRent = $this->rentPerSecond('common') ?: 1;
        $equivalents = $base / $commonRent;
        $parcels = array_sum(array_map('floatval', $counts));
        $tierAmount = $this->tierBasis() === 'rent' ? $equivalents : $parcels;
        $boostMultiplier = self::tierValue($this->boostTiers(), $tierAmount) ?: 1;
        $badgePercent = $this->badgePercent($badgeCount);
        $badgeMultiplier = 1 + $badgePercent / 100;

        $off = $base * $badgeMultiplier;
        $on = $off * $boostMultiplier;
        $perDay = $off * (24 - $boostHours) * 3600 + $on * $boostHours * 3600;

        return [
            'parcels' => (int) array_sum(array_map('intval', $counts)),
            'counts' => $counts,
            'equivalents' => $equivalents,
            'tier_amount' => $tierAmount,
            'base_per_second' => $base,
            'badge_count' => $badgeCount,
            'badge_percent' => $badgePercent,
            'boost_multiplier' => $boostMultiplier,
            'boost_hours' => $boostHours,
            'off_per_second' => $off,
            'on_per_second' => $on,
            'off_per_day' => $off * 86400,
            'on_per_day' => $on * 86400,
            'per_day' => $perDay,
            'per_month' => $perDay * 365 / 12,
            'per_year' => $perDay * 365,
            'next_boost_drop' => $this->nextBoostDrop($tierAmount),
            'next_badge_tier' => $this->nextBadgeTier($badgeCount),
        ];
    }

    /** Earnings for the current holdings and badge count. */
    public function current(?Holding $holding = null): array
    {
        $holding ??= Holding::current();
        $counts = $holding ? $holding->counts() : array_fill_keys(self::RARITIES, 0);

        return $this->calculate($counts, $this->badgesOwned());
    }

    /** 'parcels' (tiers by parcel count) or 'rent' (tiers by common-parcel equivalents). */
    public function tierBasis(): string
    {
        return ($this->settings['boost_tier_basis'] ?? 'parcels') === 'rent' ? 'rent' : 'parcels';
    }

    /** Where the next (lower) ad boost tier starts, in the tier basis units, if any. */
    public function nextBoostDrop(float $amount): ?array
    {
        foreach ($this->boostTiers() as $tier) {
            if ($tier['from'] > $amount + 1e-9) {
                return ['at' => $tier['from'], 'multiplier' => $tier['value'], 'away' => $tier['from'] - $amount];
            }
        }

        return null;
    }

    /** The next badge tier above owning $count badges, if any. */
    public function nextBadgeTier(int $count): ?array
    {
        foreach ($this->badgeTiers() as $tier) {
            if ($tier['from'] > $count) {
                return ['from' => (int) $tier['from'], 'value' => $tier['value']];
            }
        }

        return null;
    }

    /** Expected base rent of one newly bought parcel, using the rarity odds. */
    public function expectedNewParcelMix(): array
    {
        $odds = [];
        foreach (self::RARITIES as $rarity) {
            $odds[$rarity] = max(0, (float) ($this->settings['odds_'.$rarity] ?? 0));
        }
        $sum = array_sum($odds) ?: 1;

        return array_map(fn ($o) => $o / $sum, $odds);
    }

    public function parcelCost(): float
    {
        return (float) $this->settings['atlas_bucks_per_parcel'] * (float) $this->settings['cost_per_atlas_buck'];
    }

    /**
     * Compare buying parcels against getting enough badges for the next badge tiers. Each option
     * shows the extra per day, what it costs and how many days it takes to pay for itself.
     *
     * @param  int[]  $parcelAmounts
     */
    public function plan(array $counts, int $badgeCount, array $parcelAmounts = [1, 10, 25, 50, 100]): array
    {
        $now = $this->calculate($counts, $badgeCount);
        $mix = $this->expectedNewParcelMix();
        $options = [];

        foreach ($parcelAmounts as $n) {
            $newCounts = $counts;
            foreach ($mix as $rarity => $share) {
                $newCounts[$rarity] = ($newCounts[$rarity] ?? 0) + $n * $share;
            }
            $after = $this->calculate($newCounts, $badgeCount);
            $options[] = $this->option("Buy {$n} parcel".($n === 1 ? '' : 's'), 'parcels', $n * $this->parcelCost(), $now, $after);
        }

        // The next two badge tiers: how many more badges each needs and what that's worth.
        foreach (array_slice(array_filter($this->badgeTiers(), fn ($t) => $t['from'] > $badgeCount), 0, 2) as $tier) {
            $more = (int) $tier['from'] - $badgeCount;
            $after = $this->calculate($counts, (int) $tier['from']);
            $option = $this->option("Get {$more} more ".($more === 1 ? 'badge' : 'badges')." ({$tier['from']} total)", 'badge', $more * $this->badgeCost(), $now, $after);
            if ($this->badgeCost() <= 0) {
                $option['payback_days'] = null;
                $option['note'] = 'Set a cost per badge in Settings to compare';
            }
            $options[] = $option;
        }

        // Fastest payback first (rounded so float noise doesn't reorder equal options), cheapest on ties.
        usort($options, fn ($a, $b) => [$a['payback_days'] === null, round($a['payback_days'] ?? 0, 1), $a['cost']]
            <=> [$b['payback_days'] === null, round($b['payback_days'] ?? 0, 1), $b['cost']]);

        return ['now' => $now, 'options' => $options];
    }

    private function option(string $label, string $kind, float $cost, array $now, array $after): array
    {
        $gain = $after['per_day'] - $now['per_day'];

        return [
            'label' => $label,
            'kind' => $kind,
            'cost' => $cost,
            'after' => $after,
            'gain_per_day' => $gain,
            'gain_per_year' => $gain * 365,
            'payback_days' => $gain > 0 ? $cost / $gain : null,
            'boost_changed' => $after['boost_multiplier'] != $now['boost_multiplier'],
            'badge_tier_changed' => $after['badge_percent'] != $now['badge_percent'],
            'note' => null,
        ];
    }
}
