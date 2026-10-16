<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A county's leaderboard already includes its towns, so totals mustn't add them again. */
class NestedAreasTest extends TestCase
{
    use RefreshDatabase;

    private function board(string $name, string $type, ?string $region, ?string $key, array $parcels): Location
    {
        $location = Location::create(['name' => $name, 'type' => $type, 'region' => $region, 'atlas_key' => $key]);
        $snapshot = $location->snapshots()->create(['recorded_on' => '2026-10-15']);
        foreach ($parcels as $username => $count) {
            $snapshot->entries()->create(['player_id' => Player::findOrCreateByName($username)->id, 'parcels' => $count]);
        }

        return $location;
    }

    public function test_county_totals_dont_double_count_their_towns(): void
    {
        // Imported boards: Essex (county) and towns in it, plus a town in another county.
        $essex = $this->board('Essex', 'county', 'ENG', 'state/eng/ess', ['Imossi' => 4999, 'Other' => 100]);
        $basildon = $this->board('Basildon', 'village', 'ESS', 'city/eng/ess/basildon', ['Imossi' => 3019, 'TownOnly' => 7]);
        $this->board('Thurrock', 'village', 'ESS', 'city/eng/ess/thurrock', ['Imossi' => 1273]);
        $this->board('Castle Point', 'village', 'ESS', 'city/eng/ess/castle-point', ['Imossi' => 389]);
        $this->board('Canvey Island', 'village', 'ESS', 'city/eng/ess/canvey-island', ['Imossi' => 89]);
        $this->board('Bowers Gifford', 'village', 'ESS', 'city/eng/ess/bowers-gifford', ['Imossi' => 18]);
        $this->board('Marston Moretaine', 'village', 'BDF', 'city/eng/bdf/marston-moretaine', ['Imossi' => 2]);

        $this->assertTrue($essex->contains($basildon));
        $this->assertFalse($basildon->contains($essex));
        $this->assertFalse($essex->contains(Location::where('name', 'Marston Moretaine')->first()));

        // 4,999 for all of Essex, plus 2 in Bedfordshire - not 9,786.
        $imossi = Player::where('username', 'Imossi')->first();
        $this->get('/players')->assertOk()->assertSeeInOrder(['Imossi', '5,001']);
        $this->get("/players/{$imossi->id}")->assertOk()->assertSee('5,001 parcels in tracked locations')
            ->assertSee('part of Essex - not added to the total again');
        // Someone only on a town's board still has it counted.
        $this->get('/players?q=TownOnly')->assertOk()->assertSeeInOrder(['TownOnly', '7']);

        // The public home page (as a visitor).
        auth()->logout();
        $this->get('/')->assertOk()->assertSeeInOrder(['Biggest landowners', 'Imossi', '5,001']);

        // Parcels on boards: each player's total, added up - 5,001 + 100 + 7.
        $this->get('/')->assertSeeInOrder(['Parcels on boards', '5,108']);
    }

    public function test_places_added_by_hand_use_the_region_and_countries_contain_counties(): void
    {
        $essex = Location::create(['name' => 'Essex', 'type' => 'county']);
        $boreham = Location::create(['name' => 'Boreham', 'type' => 'village', 'region' => 'Essex']);
        $chelmsford = Location::create(['name' => 'Chelmsford', 'type' => 'city']); // no region given
        $england = Location::create(['name' => 'ENG', 'type' => 'country', 'atlas_key' => 'country/eng']);
        $importedEssex = Location::create(['name' => 'Essex (imported)', 'type' => 'county', 'region' => 'ENG', 'atlas_key' => 'state/eng/ess']);

        $this->assertTrue($essex->contains($boreham));       // region "Essex" matches the county's name
        $this->assertFalse($essex->contains($chelmsford));   // can't tell without a region
        $this->assertTrue($england->contains($importedEssex));
        $this->assertTrue($importedEssex->contains(Location::create(['name' => 'Basildon', 'type' => 'town', 'region' => 'ESS'])));
        $this->assertFalse($boreham->contains($essex));
    }
}
