# AE Tracker

A Laravel 13 app for tracking Atlas Earth parcels:

- **Locations & leaderboards**: add villages, towns and cities and record dated *snapshots* of who owns how many parcels. Each location shows its **Mayor** (most parcels, ties included), everyone's rank, the change since the last snapshot, the gain since each player was first seen, their share, the gap to #1, and a growth chart.
- **Players**: every player seen on a board and all the locations they currently hold parcels in. You can filter to players in 2+ or 3+ locations. Mark your own username as *me* (the "This is me" button on a player page) to get "parcels needed to become Mayor" everywhere.
- **Dashboard**: where you're Mayor, where you're closest to taking over, and which locations are due an update.
- **My Parcels**: your parcel counts by rarity over time, how many badges you have (just a number; it shows your badge tier and how many more you need for the next), and estimated earnings (per second/hour/day/month/year) on and off ad boost.
- **Planner**: compares buying 1/10/25/50/100 parcels against reaching the next badge tiers, ranked by how fast each one pays for itself (set a cost per badge in Settings for the badge payback). It flags when a purchase drops you into a lower ad-boost tier and charts earnings as you add parcels.
- **Settings**: rent per rarity, boost tiers (by parcel count or by rent), badge tiers, rarity odds and costs. The boost and UK badge tier defaults come from the game; the rent rates and odds are estimates, so check them against the game.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite      # or point DB_* in .env at MySQL
php artisan migrate
php artisan serve
```

No Node build step is needed: CSS and Chart.js are served from `public/`.

### Using MySQL

Set these in `.env` and run `php artisan migrate`:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=aetracker
DB_USERNAME=...
DB_PASSWORD=...
```

### Importing the old tracker's database

The importer reads the original app's SQLite file (`locations`, `players`, `location_players`, `parcel_snapshots`) and groups each location's counts into dated snapshots:

```bash
php artisan aetracker:import-legacy /path/to/old/database.sqlite --me=SuperSparrow
```

It is safe to run more than once.

## Importing straight from the Atlas Earth map

On the **Import** page, drag the **Send to AE Tracker** button to your bookmarks bar. Then:

1. Log in at `app.atlasearth.com/map`, go to a place and open the leaderboard (trophy button) on the town/city, county or country tab.
2. Click **Send to AE Tracker**. It reads every player on the board (scrolling the list if it loads more) and opens the tracker in a new tab.
3. Check the review page (location, date, changes since the last snapshot) and click **Save snapshot**.

The first import of a place asks which location it is, or creates one. After that the location is remembered (the `atlas_key` column), even if you rename it. Importing again on the same day replaces that day's snapshot. If the tracker tab is blocked, the bookmarklet also copies the data, which you can paste into the box on the Import page.

The bookmarklet sends to the tracker's address at the time you add it, so add it again if you move the tracker. The receiving URL (`/import/atlas-earth`) has no CSRF protection so that the Atlas Earth page can post to it, but it only stages data: saving always goes through the review page.

## Adding a snapshot quickly

The snapshot form is pre-filled with the previous snapshot, so you only change the numbers that moved. You can also paste a list with one player per line and the count last. These all work:

```
SuperSparrow 104
2. LogisticHawk - 85
Kage_uvlf,22
```

Setting a player to 0 (or leaving them blank) removes them from that snapshot.

### Anonymous players

Atlas Earth shows deleted or unnamed accounts as "Anonymous", so several in one place can't be told apart. Each "Anonymous" you enter becomes its own *ghost* player for that location only (Anonymous #1, #2…). Un-numbered ones are given the lowest free numbers, biggest holder first. The next snapshot is pre-filled with the numbers, so their history carries on, and you can type `Anonymous #2` to update a specific one. Ghosts are hidden from the Players list unless you tick *Show anonymous*, and they're deleted along with their location.

## How earnings are estimated

```
base       = Σ parcels × rent per second for that rarity
badges     = 1 + (badge tier % for the number of badges you own) / 100
boost      = ad boost multiplier for your tier: by number of parcels (default), or by base rent ÷ common rent
             ("common-parcel equivalents") if you set "Tiers are based on: Rent" in Settings
per day    = base × badges × [(24 − boosted hours) + boosted hours × boost] × 3600
SB day     = the same, with the Super Boost multiplier (e.g. 50×) instead of your tier's boost
per year   = (365 − SB days) × per day + SB days × SB day      e.g. 333d + 32SBd
per month  = per year ÷ 12                                    e.g. 27.75d + 2.67SBd
```

My Parcels shows a Base column (no ad boost) and a With ad boost column (boosted all day). If you set fewer than 24 boosted hours a day, it adds a "Your typical" column too.

The planner gives new parcels an expected rarity mix based on the odds in Settings.

## Database tables

| Table | What it holds |
|---|---|
| `locations` | name, type (village/town/city/county/state/country), region, `atlas_key` (the Atlas Earth leaderboard it was imported from), notes |
| `players` | username, `is_me`; `location_id` is set only for anonymous ghost players |
| `snapshots` | one per location per date |
| `snapshot_entries` | player + parcel count within a snapshot |
| `holdings` | your own parcel counts per rarity, by date |
| `settings` | key/value overrides of `config/aetracker.php` defaults, including `badges_owned` |

## Tests

```bash
php artisan test
```
