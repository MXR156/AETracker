<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Writes the tracker's data as MySQL/MariaDB INSERT statements, e.g. to import with phpMyAdmin.
 * Create the tables first by starting the tracker against the MySQL database (it runs its
 * migrations), then import this file into that database.
 */
class ExportMysql extends Command
{
    protected $signature = 'aetracker:export-mysql
        {path? : Where to write the .sql file (default: storage/app/aetracker-data.sql, or /data/aetracker-data.sql in Docker)}';

    protected $description = 'Export the tracker data as MySQL/MariaDB INSERT statements (e.g. for phpMyAdmin)';

    public function handle(): int
    {
        $path = $this->argument('path') ?? (is_dir('/data') ? '/data/aetracker-data.sql' : storage_path('app/aetracker-data.sql'));
        $db = DB::connection();
        $tables = array_values(array_filter(CopyDatabase::TABLES, fn ($t) => $db->getSchemaBuilder()->hasTable($t)));

        $out = fopen($path, 'w');
        if (! $out) {
            $this->error("Can't write to {$path}");

            return self::FAILURE;
        }

        fwrite($out, "-- AE Tracker data, exported ".now()->toDateTimeString()." from {$db->getDriverName()}\n");
        fwrite($out, "-- Import into a database whose tables the tracker has already created (run it against MySQL once first).\n");
        fwrite($out, "-- Existing tracker data in these tables is replaced.\n\n");
        fwrite($out, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSTART TRANSACTION;\n\n");
        foreach (array_reverse($tables) as $table) {
            fwrite($out, "DELETE FROM `{$table}`;\n");
        }

        $counts = [];
        foreach ($tables as $table) {
            $counts[$table] = 0;
            fwrite($out, "\n-- {$table}\n");
            $db->table($table)->orderBy($table === 'settings' ? 'key' : 'id')->chunk(200, function ($rows) use ($out, $table, &$counts) {
                $columns = '`'.implode('`, `', array_keys((array) $rows->first())).'`';
                $values = $rows->map(fn ($row) => '('.implode(', ', array_map([$this, 'value'], (array) $row)).')')->implode(",\n  ");
                fwrite($out, "INSERT INTO `{$table}` ({$columns}) VALUES\n  {$values};\n");
                $counts[$table] += $rows->count();
            });
            if ($table !== 'settings' && $counts[$table] > 0) {
                $next = (int) $db->table($table)->max('id') + 1;
                fwrite($out, "ALTER TABLE `{$table}` AUTO_INCREMENT = {$next};\n");
            }
        }

        fwrite($out, "\nCOMMIT;\nSET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($out);

        $this->table(['Table', 'Rows'], collect($counts)->map(fn ($n, $t) => [$t, $n])->values()->all());
        $this->info("Written to {$path}");

        return self::SUCCESS;
    }

    /** A value as a MySQL literal. */
    public function value(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return "'".str_replace(['\\', "'", "\0", "\n", "\r", "\x1a"], ['\\\\', "\\'", '\\0', '\\n', '\\r', '\\Z'], (string) $value)."'";
    }
}
