<?php

namespace Tests\Feature;

use App\Models\Holding;
use App\Models\Location;
use App\Models\Player;
use App\Models\Setting;
use App\Services\EarningsCalculator;
use App\Services\Projection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectionTest extends TestCase
{
    use RefreshDatabase;

    private function projection(array $settings = []): Projection
    {
        return new Projection(new EarningsCalculator(array_merge(config('aetracker.defaults'), [
            'explorer_club' => '1', 'premium_ladder' => '1',
            'explorer_cost_month' => '24.99', 'premium_cost_month' => '24.99',
        ], $settings)));
    }

    public function test_ab_income_matches_the_90_day_figures(): void
    {
        $p = $this->projection();

        // 448 + 294x3 + 10,505 + 1,030x3 = 14,925 AB per 90 days
        $this->assertEqualsWithDelta(14925, $p->abPerDay() * 90, 1e-9);
        $this->assertEqualsWithDelta(165.83, $p->abPerDay(), 0.01);
        $this->assertEqualsWithDelta(149.25, $p->abPerDay() * 90 / $p->abPerParcel(), 1e-9);
        $this->assertEqualsWithDelta(49.98, $p->subscriptionCostPerMonth(), 1e-9);

        // Free only: 448 + 882 = 1,330 per 90 days, no subscriptions.
        $free = $this->projection(['explorer_club' => '0', 'premium_ladder' => '0']);
        $this->assertEqualsWithDelta(1330, $free->abPerDay() * 90, 1e-9);
        $this->assertSame(0.0, $free->subscriptionCostPerMonth());
    }

    public function test_buys_whole_parcels_each_month_and_carries_the_rest(): void
    {
        $p = $this->projection(['ab_balance' => '50']);
        $result = $p->run(new Holding(['common' => 421, 'rare' => 0, 'epic' => 0, 'legendary' => 0]), 12);
        $rows = $result['rows'];

        $this->assertCount(13, $rows);
        $abPerMonth = $p->abPerDay() * 365 / 12; // ~5,044 AB
        $this->assertSame((int) floor((50 + $abPerMonth) / 100), $rows[1]['bought']);
        $this->assertEqualsWithDelta(fmod(50 + $abPerMonth, 100), $rows[1]['ab_balance'], 1e-6);

        // A year of income (plus the 50 AB) all spent: ~605 parcels.
        $this->assertSame((int) floor((50 + 12 * $abPerMonth) / 100), $rows[12]['bought_total']);
        $this->assertSame(421 + $rows[12]['bought_total'], $rows[12]['parcels']);
        $this->assertGreaterThan($rows[0]['rent_month'], $rows[12]['rent_month']);
        $this->assertEqualsWithDelta(12 * 49.98, $rows[12]['subs_total'], 1e-9);
    }

    public function test_milestones_report_the_first_month_a_target_is_reached(): void
    {
        $p = $this->projection(['rent_milestones' => '0.0001, 1000000']);
        $result = $p->run(new Holding(['common' => 421]), 6);
        $byLabel = collect($result['milestones'])->keyBy('label');

        $this->assertSame('Already there', $result['rows'][0]['month'] === 0 ? 'Already there' : null);
        $this->assertSame(0, $byLabel['Rent reaches $0.00 a month']['row']['month']);
        $this->assertNull($byLabel['Rent reaches $1,000,000.00 a month']['row']);
        $this->assertArrayHasKey('Rent covers both subscriptions', $byLabel->all());
        $this->assertArrayHasKey('Total rent earned has paid back the subscriptions', $byLabel->all());
    }

    public function test_takeover_months_with_leader_still_and_growing(): void
    {
        $me = Player::create(['username' => 'SuperSparrow', 'is_me' => true]);
        $mayor = Player::create(['username' => 'Uacesk']);
        $chelmsford = Location::create(['name' => 'Chelmsford', 'type' => 'city']);
        foreach ([[now()->subDays(61)->toDateString(), 238, 302], [now()->subDay()->toDateString(), 238, 342]] as [$date, $mine, $theirs]) {
            $s = $chelmsford->snapshots()->create(['recorded_on' => $date]);
            $s->entries()->create(['player_id' => $me->id, 'parcels' => $mine]);
            $s->entries()->create(['player_id' => $mayor->id, 'parcels' => $theirs]);
        }

        $p = $this->projection();
        $rows = $p->run(new Holding(['common' => 421]), 24)['rows'];
        $t = $p->takeovers($rows)[0];

        $this->assertSame(105, $t->gap);                       // 342 - 238 + 1
        $this->assertEqualsWithDelta(20.28, $t->growth, 0.01);  // 40 parcels in 60 days ≈ 20.3 a month
        $this->assertSame(3, $t->still['month']);               // ~50 parcels a month -> 3 months
        $this->assertGreaterThan($t->still['month'], $t->growing['month']);
    }

    public function test_page_shows_and_saves_the_projection(): void
    {
        Holding::create(['recorded_on' => now(), 'common' => 421]);

        $this->get('/projection')->assertOk()->assertSee('Your AB income')->assertSee('1,330'); // free only by default

        $this->put('/projection', [
            'ab_free_daily_90' => 448, 'ab_free_ladder_30' => 294,
            'explorer_club' => '1', 'ab_explorer_90' => 10505, 'explorer_cost_month' => 24.99,
            'premium_ladder' => '1', 'ab_premium_30' => 1030, 'premium_cost_month' => 24.99,
            'ab_extra_month' => 0, 'ab_balance' => 0, 'projection_months' => 12, 'rent_milestones' => '10, 20',
        ])->assertRedirect('/projection')->assertSessionHasNoErrors();

        $this->assertSame('1', Setting::allValues()['explorer_club']);
        $this->get('/projection')->assertOk()
            ->assertSee('14,925')->assertSee('149.3')->assertSee('$49.98')
            ->assertSee('Rent covers both subscriptions')->assertSee('Rent reaches $20.00 a month');

        // Unticking turns a source off.
        $this->put('/projection', ['ab_free_daily_90' => 448, 'ab_free_ladder_30' => 294, 'ab_explorer_90' => 10505, 'explorer_cost_month' => 0,
            'ab_premium_30' => 1030, 'premium_cost_month' => 0, 'ab_extra_month' => 0, 'ab_balance' => 0, 'projection_months' => 12]);
        $this->assertSame('0', Setting::allValues()['explorer_club']);

        // The Settings form doesn't need (or touch) the projection settings.
        $this->put('/settings', array_diff_key(config('aetracker.defaults'), array_flip(config('aetracker.page_settings'))))
            ->assertSessionHasNoErrors();
        $this->assertSame('12', Setting::allValues()['projection_months']);
    }
}
