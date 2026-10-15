<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AccountAdoption;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Copies all tracker data from a SQLite database file into the database the app is set up to use
 * (e.g. MySQL). The SQLite file itself is never changed.
 */
class CopyDatabase extends Command
{
    protected $signature = 'aetracker:copy-database
        {path : Path to the SQLite database file to copy from}
        {--replace : Empty the tracker tables in the target database first}';

    protected $description = 'Copy all tracker data from a SQLite file into the configured database (e.g. MySQL)';

    /** Tracker tables, parents before children so foreign keys are satisfied. Sessions, cache and jobs are skipped. */
    public const TABLES = ['boost_regions', 'locations', 'players', 'users', 'user_settings', 'snapshots', 'snapshot_entries', 'holdings', 'settings'];

    /** Copied over whatever the target has, rather than counting as a clash (a new database starts with a default country). */
    private const ALWAYS_REPLACED = ['boost_regions'];

    private const SOURCE = 'aetracker_copy_source';

    public function handle(): int
    {
        $path = $this->argument('path');
        if (! is_file($path) || ! is_readable($path)) {
            $this->error("Can't read {$path}");

            return self::FAILURE;
        }

        $target = DB::connection();
        $targetName = $target->getName();
        if ($target->getDriverName() === 'sqlite' && realpath($target->getDatabaseName()) === realpath($path)) {
            $this->error('That file is the database the app is already using - nothing to copy.');

            return self::FAILURE;
        }

        // Work on a temporary copy, brought up to date with the latest migrations, so the original
        // file is never changed and an older database still lines up with the current tables.
        $tmp = tempnam(sys_get_temp_dir(), 'aetracker-');
        $copy = $tmp.'.sqlite';
        copy($path, $copy);

        try {
            config(['database.connections.'.self::SOURCE => [
                'driver' => 'sqlite', 'database' => $copy, 'prefix' => '', 'foreign_key_constraints' => true,
            ]]);

            $this->line('Updating a copy of the SQLite database to the latest version…');
            $this->callSilently('migrate', ['--database' => self::SOURCE, '--force' => true]);

            $this->line("Preparing the {$target->getDriverName()} database ({$target->getDatabaseName()})…");
            $this->call('migrate', ['--database' => $targetName, '--force' => true]);

            $source = DB::connection(self::SOURCE);

            // Logins are only copied if the SQLite file has some, so an account you've already
            // made in the target database isn't counted as a clash or wiped by --replace.
            $tables = $source->table('users')->exists() ? self::TABLES : array_values(array_diff(self::TABLES, ['users', 'user_settings']));

            $notEmpty = array_filter(array_diff($tables, self::ALWAYS_REPLACED), fn ($t) => $target->table($t)->exists());
            if ($notEmpty && ! $this->option('replace')) {
                $this->error('The target database already has tracker data ('.implode(', ', $notEmpty).').');
                $this->line('Run again with --replace to empty those tables and copy over them.');

                return self::FAILURE;
            }

            $counts = [];
            $target->transaction(function () use ($source, $target, $tables, &$counts) {
                if ($this->option('replace')) {
                    Schema::connection($target->getName())->withoutForeignKeyConstraints(function () use ($target, $tables) {
                        foreach (array_reverse($tables) as $table) {
                            $target->table($table)->delete();
                        }
                    });
                }

                foreach (self::ALWAYS_REPLACED as $table) {
                    $target->table($table)->delete();
                }

                foreach ($tables as $table) {
                    $counts[$table] = 0;
                    $source->table($table)->orderBy($table === 'settings' ? 'key' : 'id')->chunk(500, function ($rows) use ($target, $table, &$counts) {
                        $target->table($table)->insert($rows->map(fn ($r) => (array) $r)->all());
                        $counts[$table] += $rows->count();
                    });
                }
            });

            $this->resetAutoIncrements($target, $tables);

            // Anyone whose chosen country didn't come across goes back to the default.
            $target->table('user_settings')->where('key', 'boost_region_id')
                ->whereNotIn('value', $target->table('boost_regions')->pluck('id')->map(fn ($id) => (string) $id))->delete();

            // Data from before per-account logins goes to the first admin, if there is one.
            if (AccountAdoption::hasOrphans() && ($admin = User::where('is_admin', true)->orderBy('id')->first())) {
                AccountAdoption::adopt($admin->id);
                $this->line("Parcels, badges, projection settings and “me” from the copy now belong to {$admin->email}.");
            }

            $this->newLine();
            $this->table(['Table', 'Rows copied'], collect($counts)->map(fn ($n, $t) => [$t, $n])->values()->all());
            $this->info('Done. The app is now using the copied data.');

            return self::SUCCESS;
        } finally {
            DB::purge(self::SOURCE);
            @unlink($copy);
            @unlink($tmp);
        }
    }

    /** Make sure new rows continue after the copied IDs (MySQL/MariaDB and PostgreSQL). */
    private function resetAutoIncrements($target, array $tables): void
    {
        foreach (array_diff($tables, ['settings']) as $table) {
            $next = (int) $target->table($table)->max('id') + 1;
            match ($target->getDriverName()) {
                'mysql', 'mariadb' => $target->statement("ALTER TABLE `{$table}` AUTO_INCREMENT = {$next}"),
                'pgsql' => $target->statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), ?, false)", [$next]),
                default => null,
            };
        }
    }
}
