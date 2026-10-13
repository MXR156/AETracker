<?php

/*
| Atlas Earth tracker defaults.
|
| Everything under "defaults" can be changed from the Settings page in the app and is
| stored in the `settings` table - these values are only used until you save your own.
| Atlas Earth changes its numbers from time to time, so check them against the game.
*/

return [

    // Location types and the title held by whoever owns the most parcels there.
    'location_types' => [
        'village' => 'Mayor',
        'town' => 'Mayor',
        'city' => 'Mayor',
        'county' => 'Governor',
        'state' => 'Governor',
        'country' => 'President',
    ],

    'rarities' => ['common', 'rare', 'epic', 'legendary'],

    'defaults' => [
        'currency_symbol' => '$',

        // Base rent per parcel, per second (in-game dollars), before any boosts.
        'rent_common' => '0.0000000011',
        'rent_rare' => '0.0000000016',
        'rent_epic' => '0.0000000022',
        'rent_legendary' => '0.0000000044',

        // Chance (%) of each rarity when you buy a new parcel - used by the planner.
        'odds_common' => '85',
        'odds_rare' => '10',
        'odds_epic' => '4',
        'odds_legendary' => '1',

        // How many hours a day you are typically on ad boost.
        'boost_hours_per_day' => '6',

        // Ad boost multiplier tiers, one line per tier: "from:multiplier". The highest "from" you meet applies.
        'boost_tiers' => "1:20\n61:15\n101:10\n151:8\n181:7\n221:6\n251:5\n301:4\n351:3\n451:2",

        // What the boost tier "from" numbers count: 'parcels' (how many parcels you own, as the game's
        // tier table shows) or 'rent' (common-parcel equivalents: base rent / common rent, so a
        // legendary counts as several commons).
        'boost_tier_basis' => 'parcels',

        // Badge rent boost by number of badges owned, one line per tier: "badges:percent".
        'badge_tiers' => "1:5\n11:10\n31:15\n61:20\n101:25",

        // How many badges you own (set on the My Parcels page).
        'badges_owned' => '0',

        // What things cost you, in real money, for the planner.
        'atlas_bucks_per_parcel' => '100',
        'cost_per_atlas_buck' => '0.01',

        // Real cost of getting one more badge, for the planner. 0 = unknown.
        'cost_per_badge' => '0',
    ],
];
