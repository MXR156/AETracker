<?php

namespace Tests\Feature;

use App\Models\Holding;
use App\Models\Location;
use App\Models\Player;
use App\Models\Setting;
use App\Services\EarningsCalculator;
use App\Services\Projection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_reward_schedules_match_the_game_totals(): void
    {
        $p = $this->projection();

        $this->assertSame(448.0, array_sum($p->loginSchedule('free')));
        $this->assertSame(10505.0, array_sum($p->loginSchedule('explorer')));
        $this->assertSame(200.0, $p->loginSchedule('free')[90]);
        $this->assertSame(90.0, $p->loginSchedule('explorer')[89]);

        $free = $p->ladder('free');
        $premium = $p->ladder('premium');
        $this->assertCount(15, $free);
        $this->assertSame(294.0, array_sum(array_column($free, 'ab')));
        $this->assertSame(1030.0, array_sum(array_column($premium, 'ab')));
        $this->assertSame(1, $free[14]['badges']);
        $this->assertSame(1, $premium[14]['legendary']);
        $this->assertSame('15 bid tokens', $free[1]['other']);

        // 448 + 294x3 + 10,505 + 1,030x3 = 14,925 AB per 90 days
        $this->assertEqualsWithDelta(14925, $p->abPer90Days(), 1e-9);
        $this->assertEqualsWithDelta(49.98, $p->subscriptionCostPerMonth(), 1e-9);

        // Free only: 448 + 882 = 1,330
        $this->assertEqualsWithDelta(1330, $this->projection(['explorer_club' => '0', 'premium_ladder' => '0'])->abPer90Days(), 1e-9);
    }

    public function test_streak_day_moves_on_and_restarts_after_day_90(): void
    {
        $p = $this->projection(['login_streak_day' => '85', 'login_streak_set_on' => '2026-10-14']);

        $this->assertSame(85, $p->streakDay(Carbon::parse('2026-10-14')));
        $this->assertSame(90, $p->streakDay(Carbon::parse('2026-10-19')));
        $this->assertSame(1, $p->streakDay(Carbon::parse('2026-10-20')));
        $this->assertSame(84, $p->streakDay(Carbon::parse('2026-10-13')));
    }

    public function test_projects_day_by_day_with_the_streak_and_ladder(): void
    {
        // Free rewards only, on day 85 of the streak on 14 Oct.
        $p = $this->projection([
            'explorer_club' => '0', 'premium_ladder' => '0',
            'login_streak_day' => '85', 'login_streak_set_on' => '2026-10-14', 'badges_owned' => '0',
        ]);
        $rows = $p->run(new Holding(['common' => 100, 'rare' => 0, 'epic' => 0, 'legendary' => 0]), 1, Carbon::parse('2026-10-14'))['rows'];

        // 15-31 Oct: streak days 86-89 (1 AB each) + day 90 (200) + days 1-12 (11 x 1 + 8 on day 7) = 223 AB.
        // The ladder only has step 15 left this month: 1 badge.
        $end = $rows[1];
        $this->assertSame('2026-10-31', $end['date']->toDateString());
        $this->assertEqualsWithDelta(223, $end['ab_total'], 1e-9);
        $this->assertSame(2, $end['bought']);
        $this->assertEqualsWithDelta(23, $end['ab_balance'], 1e-9);
        $this->assertSame(102, $end['parcels']);
        $this->assertSame(1, $end['badges']);
    }

    public function test_premium_ladder_adds_a_legendary_upgrade_and_both_ladders_pay_out(): void
    {
        $p = $this->projection(['explorer_club' => '0', 'login_streak_day' => '1', 'login_streak_set_on' => '2026-10-31', 'badges_owned' => '5']);
        $rows = $p->run(new Holding(['common' => 50, 'rare' => 0, 'epic' => 0, 'legendary' => 0]), 2, Carbon::parse('2026-10-31'))['rows'];

        $nov = $rows[2];
        $this->assertSame('2026-11-30', $nov['date']->toDateString());
        // November: both ladders in full (294 + 1,030) + free login streak days 2-31
        // (27 ordinary days x 1 + day 7: 8 + day 14: 25 + day 30: 50).
        $this->assertSame('2026-10-31', $rows[1]['date']->toDateString()); // today is a month end
        $this->assertEqualsWithDelta(1324 + 27 + 8 + 25 + 50, $nov['ab_total'], 1e-9);
        $this->assertSame(6, $nov['badges']);
        $this->assertSame(1, $nov['upgrades']);
        $this->assertGreaterThan($rows[0]['rent_month'], $nov['rent_month']);
    }

    public function test_milestones_report_the_first_month_a_target_is_reached(): void
    {
        $p = $this->projection(['rent_milestones' => '0.0001, 1000000']);
        $result = $p->run(new Holding(['common' => 421]), 6);
        $byLabel = collect($result['milestones'])->keyBy('label');

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
        $this->assertGreaterThanOrEqual(105, $t->still['bought_total']);
        $this->assertLessThanOrEqual(4, $t->still['month']);    // ~50 parcels a month
        $this->assertGreaterThan($t->still['month'], $t->growing['month']);
    }

    public function test_page_shows_and_saves_the_projection(): void
    {
        Holding::create(['recorded_on' => now(), 'common' => 421]);

        $this->get('/projection')->assertOk()->assertSee('Your AB income')->assertSee('1,330'); // free only by default

        $form = [
            'login_streak_day' => 85, 'login_cycle_days' => 90,
            'login_free_base' => 1, 'login_free_bonus' => '7:8, 14:25, 30:50, 60:80, 90:200',
            'login_explorer_base' => 90, 'login_explorer_bonus' => '7:180, 14:325, 30:500, 60:650, 90:1200',
            'ladder_free' => config('aetracker.defaults.ladder_free'), 'ladder_premium' => config('aetracker.defaults.ladder_premium'),
            'explorer_club' => '1', 'explorer_cost_month' => 24.99, 'premium_ladder' => '1', 'premium_cost_month' => 24.99,
            'ab_extra_month' => 0, 'ab_balance' => 0, 'projection_months' => 12, 'rent_milestones' => '10, 20',
        ];
        $this->put('/projection', $form)->assertRedirect('/projection')->assertSessionHasNoErrors();

        $saved = Setting::allValues();
        $this->assertSame('1', $saved['explorer_club']);
        $this->assertSame('85', $saved['login_streak_day']);
        $this->assertSame(now()->toDateString(), $saved['login_streak_set_on']);

        $this->get('/projection')->assertOk()
            ->assertSee('14,925')->assertSee('$49.98')->assertSee('value="85"', false)
            ->assertSee('day 90 (in 5 days)')
            ->assertSee('also: 15 bid tokens, 1 badge')
            ->assertSee('Rent covers both subscriptions')->assertSee('Rent reaches $20.00 a month');

        // Unticking turns a source off; a streak day beyond the cycle is rejected.
        $this->put('/projection', array_diff_key($form, ['explorer_club' => 1]))->assertSessionHasNoErrors();
        $this->assertSame('0', Setting::allValues()['explorer_club']);
        $this->put('/projection', ['login_streak_day' => 91] + $form)->assertSessionHasErrors('login_streak_day');

        // The Settings form doesn't need (or touch) the projection settings.
        $this->put('/settings', array_diff_key(config('aetracker.defaults'), array_flip(config('aetracker.page_settings'))))
            ->assertSessionHasNoErrors();
        $this->assertSame('12', Setting::allValues()['projection_months']);
    }
}
