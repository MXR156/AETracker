<?php

namespace App\Services;

use App\Models\Location;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * A leaderboard captured from the Atlas Earth web map (app.atlasearth.com/map) by the bookmarklet.
 *
 * The map shows the place as "marston moretaine, bdf, eng" and the leaderboard tab as
 * city / state / country, with a description like "Top 500 biggest marston-moretaine Landowners".
 */
class AtlasImport
{
    /** Leaderboard tab => location type used for new locations. */
    public const TAB_TYPES = ['city' => 'village', 'state' => 'county', 'country' => 'country'];

    public const MAX_ROWS = 1000;

    public function __construct(
        public string $tab,
        public string $name,
        public ?string $region,
        public string $key,
        /** @var array<int, array{rank: ?int, username: string, parcels: int, title: ?string, title_place: ?string}> */
        public array $rows,
        public array $warnings = [],
        public ?string $source = null,
    ) {}

    /** @throws InvalidArgumentException when the data isn't a usable leaderboard */
    public static function fromPayload(string $json): self
    {
        $data = json_decode($json, true);
        if (! is_array($data)) {
            throw new InvalidArgumentException('That doesn\'t look like data from the AE Tracker bookmarklet.');
        }

        $tab = strtolower((string) ($data['tab'] ?? ''));
        if ($tab === 'global') {
            throw new InvalidArgumentException('That was the global leaderboard - switch to the town/city, county or country tab and try again.');
        }
        if (! isset(self::TAB_TYPES[$tab])) {
            throw new InvalidArgumentException('Couldn\'t tell which leaderboard tab was open.');
        }

        // "marston moretaine, bdf, eng" -> city, state code, country code
        $parts = array_values(array_filter(array_map('trim', explode(',', (string) ($data['place'] ?? ''))), 'strlen'));
        [$city, $state, $country] = array_pad($parts, 3, null);
        if (count($parts) === 2) {
            [$city, $state, $country] = [$parts[0], null, $parts[1]];
        }

        $described = preg_match('/top\s+\d+\s+biggest\s+(.+?)\s+landowners/i', (string) ($data['description'] ?? ''), $m)
            ? Str::slug($m[1]) : null;

        $warnings = [];
        switch ($tab) {
            case 'city':
                $name = $city ? Str::title($city) : null;
                // The map header follows the map centre; trust the leaderboard's own description if they disagree.
                if ($described && (! $city || Str::slug($city) !== $described)) {
                    $warnings[] = "The map header said “{$city}” but the leaderboard is for “{$described}”. Check the location below.";
                    $name = Str::title(str_replace('-', ' ', $described));
                }
                $region = $state ? strtoupper($state) : null;
                $key = implode('/', array_filter(['city', Str::slug((string) $country), Str::slug((string) $state), Str::slug((string) $name)]));
                break;
            case 'state':
                $name = strtoupper((string) ($state ?: $described));
                $region = $country ? strtoupper($country) : null;
                $key = implode('/', array_filter(['state', Str::slug((string) $country), Str::slug($name)]));
                break;
            default:
                $name = strtoupper((string) ($country ?: $described));
                $region = null;
                $key = 'country/'.Str::slug($name);
        }

        if (! $name) {
            throw new InvalidArgumentException('Couldn\'t read the place name from the map.');
        }

        $rows = [];
        foreach (array_slice((array) ($data['rows'] ?? []), 0, self::MAX_ROWS) as $row) {
            $username = trim((string) ($row['username'] ?? ''));
            $parcels = $row['parcels'] ?? null;
            if ($username === '' || mb_strlen($username) > 255 || ! is_numeric($parcels) || $parcels < 0) {
                continue;
            }
            $rows[] = [
                'rank' => isset($row['rank']) && is_numeric($row['rank']) ? (int) $row['rank'] : null,
                'username' => $username,
                'parcels' => (int) $parcels,
                'title' => trim((string) ($row['title'] ?? '')) ?: null,
                'title_place' => trim((string) ($row['title_place'] ?? '')) ?: null,
            ];
        }
        if (! $rows) {
            throw new InvalidArgumentException('No players were found on that leaderboard.');
        }

        $source = filter_var($data['source'] ?? null, FILTER_VALIDATE_URL) ?: null;

        return new self($tab, $name, $region, $key, $rows, $warnings, $source);
    }

    public static function fromArray(array $a): self
    {
        return new self($a['tab'], $a['name'], $a['region'], $a['key'], $a['rows'], $a['warnings'] ?? [], $a['source'] ?? null);
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public function defaultType(): string
    {
        return self::TAB_TYPES[$this->tab];
    }

    public function tabLabel(): string
    {
        return ['city' => 'Town / city', 'state' => 'County / state', 'country' => 'Country'][$this->tab];
    }

    /** [name, parcels] lines for SnapshotWriter. */
    public function lines(): array
    {
        return array_map(fn ($r) => [$r['username'], $r['parcels']], $this->rows);
    }

    /** The existing location this leaderboard belongs to, if we can tell. */
    public function matchLocation(): ?Location
    {
        if ($found = Location::where('atlas_key', $this->key)->first()) {
            return $found;
        }

        $types = $this->tab === 'city' ? ['village', 'town', 'city'] : [$this->defaultType(), $this->tab === 'state' ? 'state' : 'country'];
        $candidates = Location::whereRaw('LOWER(name) = ?', [mb_strtolower($this->name)])
            ->whereIn('type', $types)
            ->whereNull('atlas_key')
            ->get();

        // Prefer one in the same region, then one with no region set.
        return $candidates->first(fn ($l) => $this->region && strcasecmp((string) $l->region, $this->region) === 0)
            ?? $candidates->first(fn ($l) => ! $l->region)
            ?? ($candidates->count() === 1 ? $candidates->first() : null);
    }
}
