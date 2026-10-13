<?php

namespace Tests\Unit;

use App\Http\Controllers\SnapshotController;
use App\Services\EarningsCalculator;
use PHPUnit\Framework\TestCase;

class EarningsCalculatorTest extends TestCase
{
    private function calc(array $overrides = []): EarningsCalculator
    {
        return new EarningsCalculator(array_merge([
            'currency_symbol' => '$',
            'rent_common' => '0.000001',
            'rent_rare' => '0.000002',
            'rent_epic' => '0.000003',
            'rent_legendary' => '0.000004',
            'odds_common' => '100', 'odds_rare' => '0', 'odds_epic' => '0', 'odds_legendary' => '0',
            'boost_hours_per_day' => '6',
            'boost_tiers' => "0:50\n31:40\n51:30",
            'badge_tiers' => "1:5\n11:10",
            'atlas_bucks_per_parcel' => '100',
            'cost_per_atlas_buck' => '0.01',
        ], $overrides));
    }

    public function test_tiers_are_parsed_and_looked_up(): void
    {
        $tiers = EarningsCalculator::parseTiers("31:40\n0 : 50x\nnonsense\n51=30");

        $this->assertSame([0.0, 31.0, 51.0], array_column($tiers, 'from'));
        $this->assertSame(50.0, EarningsCalculator::tierValue($tiers, 30));
        $this->assertSame(40.0, EarningsCalculator::tierValue($tiers, 31));
        $this->assertSame(30.0, EarningsCalculator::tierValue($tiers, 500));
        $this->assertSame(0.0, EarningsCalculator::tierValue(EarningsCalculator::parseTiers('1:5'), 0));
    }

    public function test_calculates_daily_earnings_with_badges_and_boost(): void
    {
        // 10 commons + 5 rares = 20 common equivalents -> 50x boost; 2 badges -> +5%.
        $r = $this->calc()->calculate(['common' => 10, 'rare' => 5, 'epic' => 0, 'legendary' => 0], 2);

        $this->assertEqualsWithDelta(0.00002, $r['base_per_second'], 1e-12);
        $this->assertEqualsWithDelta(20, $r['equivalents'], 1e-9);
        $this->assertSame(50.0, $r['boost_multiplier']);
        $this->assertSame(5.0, $r['badge_percent']);

        $off = 0.00002 * 1.05;
        $expected = $off * 18 * 3600 + $off * 50 * 6 * 3600;
        $this->assertEqualsWithDelta($expected, $r['per_day'], 1e-9);
        $this->assertEqualsWithDelta(11, $r['next_boost_drop']['away'], 1e-9);
    }

    public function test_boost_tier_is_based_on_rent_not_parcel_count(): void
    {
        // 20 legendaries = 80 common equivalents -> 30x tier even though it is only 20 parcels.
        $r = $this->calc()->calculate(['legendary' => 20], 0);

        $this->assertSame(30.0, $r['boost_multiplier']);
    }

    public function test_planner_sorts_by_payback_and_flags_tier_drops(): void
    {
        $badge = (object) ['name' => 'Cheap', 'cost' => 1.0, 'boost_percent' => 0];
        $plan = $this->calc()->plan(['common' => 30], 0, 0, [$badge], [1, 5]);

        // The first badge moves you from 0% to +5%, which beats buying parcels for $1.
        $this->assertSame('Buy badge: Cheap', $plan['options'][0]['label']);

        $five = collect($plan['options'])->firstWhere('label', 'Buy 5 parcels');
        $this->assertTrue($five['boost_changed']);
        $this->assertSame(40.0, $five['after']['boost_multiplier']);

        // Already on the first badge tier: one more badge adds nothing until the next tier.
        $plan = $this->calc()->plan(['common' => 30], 2, 0, [$badge], [1]);
        $badgeOption = collect($plan['options'])->firstWhere('kind', 'badge');
        $this->assertNull($badgeOption['payback_days']);
        $this->assertSame('No boost until you own 11 badges (+10%)', $badgeOption['note']);
    }

    public function test_paste_parser_handles_common_formats(): void
    {
        $parsed = SnapshotController::parsePaste("SuperSparrow 100\n2. LogisticHawk - 82\n#3 Kage_uvlf,20 parcels\njosh02920\t2\n\nXF89 1,203\nno number here");

        $this->assertSame([
            ['SuperSparrow', 100],
            ['LogisticHawk', 82],
            ['Kage_uvlf', 20],
            ['josh02920', 2],
            ['XF89', 1203],
        ], $parsed);
    }
}
