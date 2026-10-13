<?php

namespace Tests\Feature;

use App\Models\Holding;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortfolioTest extends TestCase
{
    use RefreshDatabase;

    public function test_holdings_badges_and_planner(): void
    {
        $this->get('/my-parcels')->assertOk()->assertSee('Start by entering');

        $this->post('/my-parcels/holdings', ['recorded_on' => '2026-10-01', 'common' => 100, 'rare' => 5, 'epic' => 2, 'legendary' => 1])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/my-parcels/holdings/add', ['rarity' => 'common', 'quantity' => 10, 'notes' => 'Boreham'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/my-parcels/holdings/add', ['rarity' => 'rare', 'quantity' => 1])->assertRedirect()->assertSessionHasNoErrors();

        // Same-day additions update today's entry instead of adding new ones.
        $this->assertSame(2, Holding::count());
        $current = Holding::current();
        $this->assertSame(110, $current->common);
        $this->assertSame(6, $current->rare);
        $this->assertSame(119, $current->total());

        $this->put('/my-parcels/badges', ['badges_owned' => 21])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('21', Setting::allValues()['badges_owned']);
        $this->put('/my-parcels/badges', ['badges_owned' => -1])->assertSessionHasErrors('badges_owned');

        $this->get('/my-parcels')->assertOk()->assertSee('119')->assertSee('+10% rent')->assertSee('10 more for +15%');
        $this->get('/my-parcels/planner?amounts=1,10&boost_hours=4')
            ->assertOk()
            ->assertSee('Buy 10 parcels')
            ->assertSee('Get 10 more badges (31 total)');

        // Saving the settings form doesn't touch the badge count.
        $this->put('/settings', array_diff_key(config('aetracker.defaults'), ['badges_owned' => 1]))->assertSessionHasNoErrors();
        $this->assertSame('21', Setting::allValues()['badges_owned']);
    }

    public function test_earnings_table_matches_the_reference_layout(): void
    {
        Setting::put('badges_owned', '5');
        $this->post('/my-parcels/holdings', ['recorded_on' => '2026-10-01', 'common' => 1, 'rare' => 0, 'epic' => 0, 'legendary' => 0]);

        $this->get('/my-parcels')->assertOk()
            ->assertSee('includes your +5% badge boost')
            ->assertSee('With ad boost (20×)')
            ->assertSee('$0.00000419580')
            ->assertSee('$0.00008391600')
            ->assertSee('$0.01409788800')
            ->assertSee('(27.75d + 2.67SBd)', false)
            ->assertSee('$0.06931461600')
            ->assertSee('(333.00d + 32.00SBd)', false)
            ->assertSee('$0.83177539200')
            ->assertDontSee('Your typical');
    }

    public function test_all_earnings_are_shown_to_11_decimal_places(): void
    {
        Setting::put('badges_owned', '21');
        $this->post('/my-parcels/holdings', ['recorded_on' => '2026-10-01', 'common' => 5000, 'rare' => 0, 'epic' => 0, 'legendary' => 0]);

        foreach (['/my-parcels', '/my-parcels/planner', '/'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            preg_match_all('/\$(\d[\d,]*)\.(\d+)/', strip_tags($html), $m);
            $this->assertNotEmpty($m[0], $url);
            foreach ($m[0] as $i => $amount) {
                // Costs (e.g. "A parcel costs $1.00") keep 2 places; every earnings figure has 11.
                $this->assertContains(strlen($m[2][$i]), [2, 4, 11], "{$url}: {$amount}");
            }
            $this->assertMatchesRegularExpression('/\$\d[\d,]*\.\d{11}/', strip_tags($html), $url);
        }

        // Yearly earnings over $1 still get 11 places.
        $this->get('/my-parcels')->assertSee('/ year')->assertDontSeeText('$97.8330');
        $this->assertMatchesRegularExpression('/\$[1-9][\d,]*\.\d{11}/', strip_tags($this->get('/my-parcels')->getContent()));
    }

    public function test_planner_has_boosted_and_base_charts(): void
    {
        $this->post('/my-parcels/holdings', ['recorded_on' => '2026-10-01', 'common' => 100, 'rare' => 0, 'epic' => 0, 'legendary' => 0]);

        $html = $this->get('/my-parcels/planner')->assertOk()
            ->assertSee('curve-boosted')->assertSee('curve-base')
            ->assertDontSee('Your typical day')               // 24h boosted by default
            ->getContent();
        preg_match('/const c = (\[.*?\]);/s', $html, $m);
        $first = json_decode($m[1], true)[0];
        $this->assertSame(100, $first['parcels']);
        $this->assertEqualsWithDelta($first['base'] * 15, $first['boosted'], 1e-12); // 100 parcels = 15x tier (61-100)

        $this->get('/my-parcels/planner?boost_hours=6')->assertOk()->assertSee('Your typical day (6h boosted)');
    }

    public function test_settings_save_and_reset(): void
    {
        $this->put('/settings', array_merge(config('aetracker.defaults'), ['boost_hours_per_day' => 8]))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('8', Setting::allValues()['boost_hours_per_day']);

        $this->put('/settings', array_merge(config('aetracker.defaults'), ['boost_tiers' => 'rubbish']))
            ->assertSessionHasErrors('boost_tiers');

        $this->put('/settings', array_merge(config('aetracker.defaults'), ['boost_tier_basis' => 'rent']))
            ->assertSessionHasNoErrors();
        $this->assertSame('rent', Setting::allValues()['boost_tier_basis']);
        $this->get('/settings')->assertOk()->assertSee('Tiers are based on');

        $this->put('/settings', ['reset' => 1]);
        $this->assertSame(config('aetracker.defaults.boost_hours_per_day'), Setting::allValues()['boost_hours_per_day']);
    }
}
