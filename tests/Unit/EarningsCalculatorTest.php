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
        // Tiers count parcels by default: 15 parcels, so the 40x tier starts 16 parcels away.
        $this->assertEqualsWithDelta(16, $r['next_boost_drop']['away'], 1e-9);
    }

    public function test_boost_tier_can_be_based_on_rent_instead_of_parcel_count(): void
    {
        // 20 legendaries = 80 common equivalents -> 30x tier on rent, 50x on parcel count.
        $this->assertSame(30.0, $this->calc(['boost_tier_basis' => 'rent'])->calculate(['legendary' => 20], 0)['boost_multiplier']);
        $this->assertSame(50.0, $this->calc()->calculate(['legendary' => 20], 0)['boost_multiplier']);
    }

    public function test_real_tier_table_with_421_parcels_is_3x(): void
    {
        $settings = [
            'rent_common' => '0.0000000011', 'rent_rare' => '0.0000000016', 'rent_epic' => '0.0000000022', 'rent_legendary' => '0.0000000044',
            'boost_tiers' => "1:20\n61:15\n101:10\n151:8\n181:7\n221:6\n251:5\n301:4\n351:3\n451:2",
        ];
        $counts = ['common' => 370, 'rare' => 35, 'epic' => 12, 'legendary' => 4]; // 421 parcels, ~454 common equivalents

        $byParcels = $this->calc($settings)->calculate($counts, 0);
        $this->assertSame(3.0, $byParcels['boost_multiplier']);
        $this->assertEqualsWithDelta(30, $byParcels['next_boost_drop']['away'], 1e-9);

        $this->assertSame(2.0, $this->calc($settings + ['boost_tier_basis' => 'rent'])->calculate($counts, 0)['boost_multiplier']);
    }

    public function test_planner_compares_parcels_with_reaching_the_next_badge_tiers(): void
    {
        // 30 commons, no badges. Reaching 1 badge (+5%) for $1 beats buying parcels.
        $plan = $this->calc(['cost_per_badge' => '1'])->plan(['common' => 30], 0, [1, 5]);
        $this->assertSame('Get 1 more badge (1 total)', $plan['options'][0]['label']);
        $this->assertSame(1.0, $plan['options'][0]['cost']);

        // Next tier after that is 11 badges -> 11 more.
        $this->assertNotNull(collect($plan['options'])->firstWhere('label', 'Get 11 more badges (11 total)'));

        $five = collect($plan['options'])->firstWhere('label', 'Buy 5 parcels');
        $this->assertTrue($five['boost_changed']);
        $this->assertSame(40.0, $five['after']['boost_multiplier']);

        // With no badge cost set, badge options show the gain but no payback.
        $plan = $this->calc()->plan(['common' => 30], 2, [1]);
        $badgeOption = collect($plan['options'])->firstWhere('kind', 'badge');
        $this->assertSame('Get 9 more badges (11 total)', $badgeOption['label']);
        $this->assertNull($badgeOption['payback_days']);
        $this->assertGreaterThan(0, $badgeOption['gain_per_day']);
    }

    public function test_badge_count_picks_the_tier(): void
    {
        $calc = $this->calc(['badge_tiers' => "1:5\n11:10\n31:15\n61:20\n101:25", 'badges_owned' => '21']);

        $this->assertSame(21, $calc->badgesOwned());
        $r = $calc->calculate(['common' => 10], $calc->badgesOwned());
        $this->assertSame(10.0, $r['badge_percent']);
        $this->assertSame(['from' => 31, 'value' => 15.0], $r['next_badge_tier']);
        $this->assertSame(0.0, $calc->badgePercent(0));
        $this->assertSame(25.0, $calc->badgePercent(500));
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
