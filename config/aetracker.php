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

        // Ad boost multiplier tiers. Atlas Earth sets your tier from your base rent per second, so tiers
        // are given in "common parcel equivalents" (base rent / common rent). One line per tier:
        // "from:multiplier". The highest "from" that you meet applies.
        'boost_tiers' => "0:50\n31:40\n51:30\n151:20\n251:15\n501:10\n1001:5",

        // Badge rent boost by number of badges owned, one line per tier: "badges:percent".
        'badge_tiers' => "1:5\n11:10\n26:15\n51:20\n101:25\n151:30",

        // What things cost you, in real money, for the planner.
        'atlas_bucks_per_parcel' => '100',
        'cost_per_atlas_buck' => '0.01',
    ],
];
