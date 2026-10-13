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
