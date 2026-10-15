<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Player;
use App\Services\AtlasImport;
use App\Services\Leaderboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AtlasImportTest extends TestCase
{
    use RefreshDatabase;

    /** What the bookmarklet sends for the Marston Moretaine town leaderboard. */
    private function payload(array $overrides = [], ?array $rows = null): string
    {
        return json_encode(array_merge([
            'source' => 'https://app.atlasearth.com/map',
            'tab' => 'city',
            'place' => 'marston moretaine, bdf, eng',
            'description' => 'Top 500 biggest marston-moretaine Landowners',
            'rows' => $rows ?? [
                ['rank' => 1, 'username' => 'neiloftheshire', 'parcels' => 60, 'title' => 'Governor', 'title_place' => 'Bedfordshire'],
                ['rank' => 2, 'username' => 'NPCandy', 'parcels' => 54, 'title' => 'Mayor', 'title_place' => 'Beetzendorf'],
                ['rank' => 3, 'username' => 'zacwyiq', 'parcels' => 25],
                ['rank' => 19, 'username' => 'Anonymous', 'parcels' => 1],
                ['rank' => 21, 'username' => 'Anonymous', 'parcels' => 1],
                ['rank' => 24, 'username' => 'Anonymous', 'parcels' => 1],
            ],
        ], $overrides));
    }

    private function receive(string $payload): string
    {
        $response = $this->post('/import/atlas-earth', ['payload' => $payload])->assertRedirect();

        return $response->headers->get('Location');
    }

    public function test_import_creates_a_location_then_matches_it_next_time(): void
    {
        $review = $this->receive($this->payload());
        $this->assertStringContainsString('/import/', $review);

        $this->get($review)->assertOk()
            ->assertSee('Marston Moretaine')
            ->assertSee('Create a new location')
            ->assertSee('Governor of Bedfordshire');

        $this->post($review, ['location' => 'new', 'name' => 'Marston Moretaine', 'type' => 'village', 'region' => 'BDF', 'recorded_on' => '2026-10-01'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $location = Location::where('name', 'Marston Moretaine')->firstOrFail();
        $this->assertSame('city/eng/bdf/marston-moretaine', $location->atlas_key);
        $board = new Leaderboard($location);
        $this->assertSame('neiloftheshire', $board->leader()->player->username);
        $this->assertSame(6, $board->rows->count()); // three separate anonymous players
        $this->assertSame(3, Player::where('location_id', $location->id)->count());

        // The token is single use.
        $this->get($review)->assertNotFound();

        // Rename the location: the next import still finds it, and shows the changes.
        $location->update(['name' => 'Marston M.']);
        $review = $this->receive($this->payload(rows: [
            ['username' => 'NPCandy', 'parcels' => 70],
            ['username' => 'neiloftheshire', 'parcels' => 61],
            ['username' => 'Newcomer', 'parcels' => 2],
            ['username' => 'Anonymous', 'parcels' => 1],
        ]));
        $this->get($review)->assertOk()->assertSee('matched')->assertSee('+16')->assertSee('No longer on the board');

        $this->post($review, ['location' => $location->id, 'recorded_on' => '2026-10-08'])->assertRedirect()->assertSessionHasNoErrors();

        $board = new Leaderboard($location->fresh());
        $this->assertSame('NPCandy', $board->leader()->player->username);
        $this->assertSame(16, $board->leader()->change);
        $this->assertSame(2, $board->snapshots->count());
    }

    public function test_matches_an_existing_location_by_name_and_learns_the_link(): void
    {
        $existing = Location::create(['name' => 'Marston Moretaine', 'type' => 'village']);

        $review = $this->receive($this->payload());
        $this->get($review)->assertOk()->assertSee('Matched automatically by name');
        $this->post($review, ['location' => $existing->id, 'recorded_on' => '2026-10-01'])->assertRedirect();

        $this->assertSame('city/eng/bdf/marston-moretaine', $existing->fresh()->atlas_key);
        $this->assertSame(1, Location::count());
    }

    public function test_saving_twice_on_the_same_day_replaces_the_snapshot(): void
    {
        $location = Location::create(['name' => 'Marston Moretaine', 'type' => 'village']);
        foreach ([60, 65] as $parcels) {
            $review = $this->receive($this->payload(rows: [['username' => 'neiloftheshire', 'parcels' => $parcels]]));
            $this->post($review, ['location' => $location->id, 'recorded_on' => '2026-10-01'])->assertRedirect();
        }

        $board = new Leaderboard($location->fresh());
        $this->assertSame(1, $board->snapshots->count());
        $this->assertSame(65, $board->leader()->parcels);
    }

    public function test_reads_county_and_country_tabs_and_rejects_bad_data(): void
    {
        $state = AtlasImport::fromPayload($this->payload(['tab' => 'state', 'description' => 'Top 500 biggest bdf Landowners']));
        $this->assertSame(['BDF', 'county', 'state/eng/bdf'], [$state->name, $state->defaultType(), $state->key]);

        $country = AtlasImport::fromPayload($this->payload(['tab' => 'country', 'description' => 'Top 500 biggest eng Landowners']));
        $this->assertSame(['ENG', 'country'], [$country->name, $country->defaultType()]);

        // If the map has moved away from the leaderboard's town, trust the leaderboard.
        $moved = AtlasImport::fromPayload($this->payload(['place' => 'cranfield, bdf, eng']));
        $this->assertSame('Marston Moretaine', $moved->name);
        $this->assertNotEmpty($moved->warnings);

        $this->post('/import/atlas-earth', ['payload' => $this->payload(['tab' => 'global'])])
            ->assertRedirect('/import')->assertSessionHasErrors('payload');
        $this->post('/import/atlas-earth', ['payload' => 'not json'])->assertSessionHasErrors('payload');
        $this->post('/import/atlas-earth', ['payload' => $this->payload(rows: [])])->assertSessionHasErrors('payload');
    }

    public function test_import_page_has_a_bookmarklet_pointing_at_this_tracker(): void
    {
        $html = $this->get('/import')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/href="javascript:[^"]+"/', $html);
        preg_match('/href="(javascript:[^"]+)"/', $html, $m);
        $code = rawurldecode(html_entity_decode(substr($m[1], 11)));
        $this->assertStringContainsString(route('import.receive'), $code);
        $this->assertStringNotContainsString('__TARGET__', $code);
    }

    private function bookmarkletCode(string $html): string
    {
        preg_match('/href="(javascript:[^"]+)"/', $html, $m);

        return rawurldecode(html_entity_decode(substr($m[1], 11)));
    }

    public function test_https_app_url_makes_the_bookmarklet_send_to_https(): void
    {
        // Behind Cloudflare the app is reached over plain http, but APP_URL says https.
        config(['app.url' => 'https://aetracker.example.com']);
        (new \App\Providers\AppServiceProvider($this->app))->boot();

        $html = $this->get('/import')->assertOk()->getContent();
        $this->assertStringContainsString("var target = 'https://", $this->bookmarkletCode($html));
        $this->assertStringNotContainsString('form isn', $html);
    }

    public function test_warns_when_viewed_over_https_but_the_bookmarklet_would_use_http(): void
    {
        config(['app.url' => 'http://raspberrypi.local:8080']);

        // What Cloudflare adds when the visitor is on https.
        $html = $this->get('/import', ['CF-Visitor' => '{"scheme":"https"}'])->assertOk()->getContent();
        $this->assertStringContainsString("var target = 'http://", $this->bookmarkletCode($html));
        $this->assertStringContainsString('would send to an insecure', $html);

        // No warning on a plain http setup (e.g. on your own network).
        $this->get('/import')->assertOk()->assertDontSee('would send to an insecure');
    }
}
