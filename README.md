# AE Tracker

A Laravel 13 app for tracking Atlas Earth parcels:

- **Locations & leaderboards**: add villages, towns and cities and record dated *snapshots* of who owns how many parcels. Each location shows its **Mayor** (most parcels, ties included), everyone's rank, the change since the last snapshot, the gain since each player was first seen, their share, the gap to #1, and a growth chart.
- **Players**: every player seen on a board and all the locations they currently hold parcels in. You can filter to players in 2+ or 3+ locations. Mark your own username as *me* (the "This is me" button on a player page) to get "parcels needed to become Mayor" everywhere.
- **Dashboard**: the one place to update your parcels (exact counts by rarity) and your badge count (just a number; it shows your badge tier and how many more you need for the next). It also shows your stats, estimated earnings (per second/hour/day/week/month/year, base and with ad boost), where you're Mayor, where you're closest to taking over, which locations are due an update, and your parcel history.
- **Planner**: compares buying 1/10/25/50/100 parcels against reaching the next badge tiers, ranked by how fast each one pays for itself (set a cost per badge in Settings for the badge payback). It flags when a purchase drops you into a lower ad-boost tier and charts earnings as you add parcels.
- **Projection**: tick whether you're in the Explorer Club and/or on the Premium Rewards Ladder, say which day of the 90-day login streak you're on, and enter your subscription prices. The projection then goes day by day: each day's login reward (1 AB a day with bonuses on days 7, 14, 30, 60 and 90; Explorer Club 90 a day with bigger bonuses), one reward-ladder step a day from the 1st of each month (including the free ladder's badge and the premium ladder's legendary upgrade), and any other AB. Every 100 AB buys a parcel and rent is recalculated (boost tiers, badges and Super Boost days included). It shows when your monthly rent covers each subscription, when total rent has paid back the subscriptions, when rent reaches your targets (e.g. $10/$20/$30/$50 a month), and how many months until you could become Mayor where you're chasing. All the reward schedules can be edited on the page.

  Add when you started playing and it also shows **your progress so far**: AB put into parcels (100 each) and bought badges (200 each; the reward ladder's monthly badge is counted as free), your pace per 90 days and per month compared with your reward schedule, and your rarity mix. You can then project at **your pace so far** instead of the reward schedule, and have new parcels follow **your own rarity mix** instead of the odds in Settings.
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

### Running on a Raspberry Pi with Docker

Every push to GitHub runs the tests and then builds a Docker image for 64-bit and 32-bit Raspberry Pi OS (and normal PCs). It's published as `ghcr.io/mxr156/aetracker` (`latest` is the default branch). On the Pi, in this repo's folder:

```bash
docker compose pull
docker compose up -d
```

Then create your login (the first account is always an admin) and open `http://<your-pi>:8080`:

```bash
docker compose exec aetracker php artisan aetracker:user you@example.com --name=YourName
```

Your database and app key are kept in `./data`; back that folder up.

**To update** after new changes are pushed and the GitHub build finishes: `docker compose pull && docker compose up -d`. Database changes are applied automatically when the container starts.

**If the pull says "denied" or "unauthorized"**, the package is private (packages from private repos are). Either make it public (GitHub → your profile → Packages → aetracker → Package settings → Change visibility), or log the Pi in once with a [personal access token](https://github.com/settings/tokens) that has the `read:packages` scope:

```bash
echo YOUR_TOKEN | docker login ghcr.io -u mxr156 --password-stdin
```

**Settings** are in `docker-compose.yml`: the port (`8080:80`), `APP_URL` and `APP_TIMEZONE` (`Europe/London`, so "today" for the login streak is right).

**Importing the old database**: copy it into `./data`, then:

```bash
docker compose exec aetracker php artisan aetracker:import-legacy /data/old-database.sqlite --me=SuperSparrow
```

**The Atlas Earth bookmarklet** sends the leaderboard from the `https` Atlas Earth site to the tracker. If the tracker is plain `http` (as above), your browser may warn that the form isn't secure; choose to send it anyway. That warning doesn't appear if you put the tracker behind HTTPS (e.g. a reverse proxy).

### Using MySQL / MariaDB instead of SQLite

**With Docker**, either:

- use your existing MySQL/MariaDB server: add these to `environment:` in `docker-compose.yml` (create the database and user first). `DB_HOST` can't be `localhost` - inside the container that's the container itself - so use the server's IP, or `host.docker.internal` plus `extra_hosts: ["host.docker.internal:host-gateway"]` if it runs on the Pi itself:

  ```yaml
      DB_CONNECTION: mariadb      # or mysql
      DB_HOST: 192.168.1.50
      DB_PORT: "3306"
      DB_DATABASE: aetracker
      DB_USERNAME: aetracker
      DB_PASSWORD: your-password
  ```

- or run the tracker with its own MariaDB using `docker-compose.mariadb.yml` (change its passwords first; needs a 64-bit OS on the Pi): `docker compose -f docker-compose.mariadb.yml up -d`.

The tracker waits for the database to be ready and creates the tables when it starts.

**Without Docker**, set the same `DB_*` values in `.env` and run `php artisan migrate`.

### Logins

Every page needs a login; there's no sign-up page, so only people you add can get in.

- Create the first account (an admin) with `php artisan aetracker:user you@example.com --name=YourName` (prefix with `docker compose exec aetracker` for Docker). Running it again for an existing email resets that password.
- Admins add and remove people, reset passwords and make others admins on the **Users** page. Members can use everything except that page.
- Everyone can change their name, email and password by clicking their name at the top.
- Five wrong passwords in a row pause logins for that email for a minute.
- The bookmarklet still works: the leaderboard is held until you review it, and the review page asks you to log in first if you aren't.

**Making it public**: put it behind HTTPS (e.g. Nginx Proxy Manager, Caddy or a Cloudflare Tunnel) so passwords aren't sent in plain text, and add these to `environment:` in `docker-compose.yml`:

```yaml
      APP_URL: "https://tracker.example.com"
      TRUSTED_PROXIES: "*"          # the proxy's IP, or * - so the app sees https:// and real visitor IPs
      SESSION_SECURE_COOKIE: "true" # only send the login cookie over https
```

### Converting your SQLite data to MySQL / MariaDB

Once the tracker is pointed at MySQL/MariaDB (above), copy everything from your SQLite file into it - locations, players (including anonymous ones), snapshots, your parcel counts and all settings, keeping the same IDs. Your SQLite file isn't changed, and an older one is brought up to date on the way.

```bash
# Docker: put the SQLite file in ./data first (e.g. data/database.sqlite from the SQLite setup)
docker compose exec aetracker php artisan aetracker:copy-database /data/database.sqlite

# Without Docker
php artisan aetracker:copy-database /path/to/database.sqlite
```

It won't copy over existing tracker data unless you add `--replace`, which empties the tracker's tables in MySQL first. Logins are only copied if the SQLite file has any, so an account you've already made in MySQL is kept.

**Prefer phpMyAdmin?** A SQLite dump (`sqlite3 … .dump`) won't import into MySQL - the SQL dialects differ. Instead, start the tracker against MySQL once so it creates the tables, then export your SQLite data as MySQL `INSERT`s and import that file in phpMyAdmin (Import tab, on the tracker's database):

```bash
# Docker, while still on SQLite: writes ./data/aetracker-data.sql
docker compose exec aetracker php artisan aetracker:export-mysql
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

The dashboard shows a Base column (no ad boost) and a With ad boost column (boosted all day). If you set fewer than 24 boosted hours a day, it adds a "Your typical" column too.

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
