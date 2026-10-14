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

    // Settings changed on their own pages (Dashboard, Projection), not on the Settings form.
    'page_settings' => [
        'badges_owned',
        'login_cycle_days', 'login_streak_day', 'login_streak_set_on', 'login_free_base', 'login_free_bonus',
        'login_explorer_base', 'login_explorer_bonus', 'ladder_free', 'ladder_premium',
        'explorer_club', 'explorer_cost_month', 'premium_ladder', 'premium_cost_month', 'ab_extra_month', 'ab_balance',
        'projection_months', 'rent_milestones',
    ],

    'defaults' => [
        'currency_symbol' => '$',

        // Base rent per parcel, per second (in-game dollars), before any boosts.
        'rent_common' => '0.00000000111',
        'rent_rare' => '0.0000000016',
        'rent_epic' => '0.0000000022',
        'rent_legendary' => '0.0000000044',

        // Chance (%) of each rarity when you buy a new parcel - used by the planner.
        'odds_common' => '85',
        'odds_rare' => '10',
        'odds_epic' => '4',
        'odds_legendary' => '1',

        // How many hours a day you are typically on ad boost (24 = always boosted).
        'boost_hours_per_day' => '24',

        // Super Boost days: days a year when the ad boost is raised to this multiplier for everyone.
        'super_boost_days_per_year' => '32',
        'super_boost_multiplier' => '50',

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

        // Projection: where Atlas Bucks come from.
        // Daily login streak: a 90-day cycle that resets after day 90 (or if you miss a day).
        // "base" is the AB on an ordinary day; "bonus" lists the special days as "day:AB".
        'login_cycle_days' => '90',
        'login_streak_day' => '1',        // which day of the streak you were on...
        'login_streak_set_on' => '',      // ...on this date (it moves on by itself each day)
        'login_free_base' => '1',
        'login_free_bonus' => '7:8, 14:25, 30:50, 60:80, 90:200',            // 448 AB per 90 days
        'login_explorer_base' => '90',
        'login_explorer_bonus' => '7:180, 14:325, 30:500, 60:650, 90:1200', // 10,505 AB per 90 days

        // Reward ladders: restart every month, one step a day from the 1st. A number is AB;
        // "badge" adds a badge and "legendary" a legendary parcel upgrade; anything else is ignored.
        'ladder_free' => '5, 15 bid tokens, 10, 12, 12, 15, 15, 15, 15, 20, 25, 40, 50, 60, 1 badge',
        'ladder_premium' => '20, 20 diamonds, 30, 35, 35, 40, 50, 55, 60, 75, 100, 150, 180, 200, 1 legendary parcel upgrade',

        'explorer_club' => '0',           // 1 = I'm in Explorer Club
        'explorer_cost_month' => '0',     // Explorer Club subscription, per month
        'premium_ladder' => '0',          // 1 = I'm on the Premium Rewards Ladder
        'premium_cost_month' => '0',      // Premium Rewards Ladder subscription, per month
        'ab_extra_month' => '0',          // anything else (Arcade, spins, commissions), per month
        'ab_balance' => '0',              // AB you have right now
        'projection_months' => '24',
        'rent_milestones' => '10, 20, 30, 50', // monthly rent targets to show
    ],
];
