# AE Tracker

A Laravel 13 app for tracking Atlas Earth parcels:

- **Locations & leaderboards**: add villages, towns and cities and record dated *snapshots* of who owns how many parcels. Each location shows its **Mayor** (most parcels, ties included), everyone's rank, the change since the last snapshot, the gain since each player was first seen, their share, the gap to #1, and a growth chart.
- **Players**: every player seen on a board and all the locations they currently hold parcels in. You can filter to players in 2+ or 3+ locations. Mark your own username as *me* (the "This is me" button on a player page) to get "parcels needed to become Mayor" everywhere.
- **Dashboard**: where you're Mayor, where you're closest to taking over, and which locations are due an update.
- **My Parcels**: your parcel counts by rarity over time, your badges, and estimated earnings (per second/hour/day/month/year) on and off ad boost.
- **Planner**: compares buying 1/10/25/50/100 parcels against buying badges, ranked by how fast each one pays for itself. It flags when a purchase drops you into a lower ad-boost tier and charts earnings as you add parcels.
- **Settings**: rent per rarity, boost tiers, badge tiers, rarity odds and costs. **The defaults are estimates.** Check them against the game and update them.

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

## Adding a snapshot quickly

The snapshot form is pre-filled with the previous snapshot, so you only change the numbers that moved. You can also paste a list with one player per line and the count last. These all work:

```
SuperSparrow 104
2. LogisticHawk - 85
Kage_uvlf,22
```

Setting a player to 0 (or leaving them blank) removes them from that snapshot.

## How earnings are estimated

```
base       = Σ parcels × rent per second for that rarity
badges     = 1 + (badge tier % for the number of badges you own + any per-badge extra %) / 100
boost      = ad boost multiplier for your tier (tiers use base rent ÷ common rent, i.e. "common-parcel equivalents")
per day    = base × badges × [(24 − boosted hours) + boosted hours × boost] × 3600
```

The planner gives new parcels an expected rarity mix based on the odds in Settings.

## Database tables

| Table | What it holds |
|---|---|
| `locations` | name, type (village/town/city/county/state/country), region, notes |
| `players` | username, `is_me` |
| `snapshots` | one per location per date |
| `snapshot_entries` | player + parcel count within a snapshot |
| `holdings` | your own parcel counts per rarity, by date |
| `badges` | badges you own or might buy (extra %, cost) |
| `settings` | key/value overrides of `config/aetracker.php` defaults |

## Tests

```bash
php artisan test
```
