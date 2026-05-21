<?php

namespace Nomanurrahman\FreshSeed\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Console\ConfirmableTrait;

class FreshSeedCommand extends Command
{
    use ConfirmableTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fresh:seed 
        {--table= : The table or comma-separated tables to be truncated and seeded} 
        {--seeder= : The seeder class to run (only valid when refreshing a single table)} 
        {--group= : The pre-configured table group to refresh}
        {--safe : Use DELETE instead of TRUNCATE to avoid implicit commits and support database transactions}
        {--dry-run : Print the operations that would be performed without actually executing them}
        {--database= : The database connection to use}
        {--force : Force the operation to run when in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Truncate and re-seed database tables with advanced options';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if (! $this->confirmToProceed()) {
            return 1;
        }

        $connection = $this->option('database') ?: config('fresh-seed.connection');
        $seederClass = $this->option('seeder');
        $isSafe = $this->option('safe');
        $isDryRun = $this->option('dry-run');

        // Resolve tables to process: key is table, value is specific seeder (if mapped) or null
        $tablesToProcess = [];

        if ($this->option('group')) {
            $groupName = $this->option('group');
            $groups = config('fresh-seed.groups', []);

            if (!isset($groups[$groupName])) {
                $this->error("Table group [{$groupName}] is not defined in config.");
                return 1;
            }

            $groupTables = $groups[$groupName];
            foreach ($groupTables as $key => $value) {
                if (is_numeric($key)) {
                    $tablesToProcess[$value] = null;
                } else {
                    $tablesToProcess[$key] = $value;
                }
            }
        } elseif ($this->option('table')) {
            $tables = explode(',', $this->option('table'));
            foreach ($tables as $table) {
                $table = trim($table);
                if ($table) {
                    $tablesToProcess[$table] = null;
                }
            }
        } else {
            // Interactive Mode
            $allTables = $this->getAllTables($connection);
            if (empty($allTables)) {
                $this->error('No tables found in the database connection.');
                return 1;
            }

            $selectedTables = $this->choice(
                'Which table(s) would you like to refresh? (comma separated numbers/names)',
                $allTables,
                null,
                null,
                true
            );

            foreach ($selectedTables as $table) {
                $tablesToProcess[$table] = null;
            }
        }

        if (empty($tablesToProcess)) {
            $this->error('No tables specified or selected for refreshing.');
            return 1;
        }

        // Validation: --seeder can only be used with a single table
        if ($seederClass && count($tablesToProcess) > 1) {
            $this->error('The --seeder option can only be used when refreshing a single table.');
            return 1;
        }

        $dbConnection = DB::connection($connection);
        $schemaConnection = Schema::connection($connection);

        $connectionName = $connection ?: 'default';

        foreach ($tablesToProcess as $table => $mappedSeeder) {
            if (!$schemaConnection->hasTable($table)) {
                $this->error("Table [{$table}] does not exist.");
                return 1;
            }

            // Determine the seeder to run
            $currentSeeder = $mappedSeeder;
            if (!$currentSeeder) {
                $currentSeeder = $seederClass ?: $this->guessSeederClass($table, $connection);
            }

            if ($isDryRun) {
                $actionType = $isSafe ? 'delete all records from' : 'truncate';
                $this->info("[Dry Run] Would {$actionType} table: {$table}");
                if ($currentSeeder) {
                    $this->info("[Dry Run] Would seed table using seeder: {$currentSeeder}");
                } else {
                    $this->warn("[Dry Run] No seeder found or specified for table: {$table}");
                }
                continue;
            }

            if ($isSafe) {
                $this->info("Deleting all records from table: {$table}");
            } else {
                $this->info("Truncating table: {$table}");
            }

            // Disable foreign key constraints to allow truncation/deletion
            $schemaConnection->disableForeignKeyConstraints();
            try {
                if ($isSafe) {
                    $dbConnection->table($table)->delete();
                } else {
                    $dbConnection->table($table)->truncate();
                }
            } finally {
                $schemaConnection->enableForeignKeyConstraints();
            }

            if ($isSafe) {
                $this->info("Table [{$table}] records deleted successfully.");
            } else {
                $this->info("Table [{$table}] truncated successfully.");
            }

            if ($currentSeeder && (class_exists($currentSeeder) || class_exists("Database\\Seeders\\{$currentSeeder}"))) {
                // If it is namespaced Database\Seeders but the class name was passed without it
                if (!class_exists($currentSeeder) && class_exists("Database\\Seeders\\{$currentSeeder}")) {
                    $currentSeeder = "Database\\Seeders\\{$currentSeeder}";
                }
                $this->info("Seeding table using: {$currentSeeder}");
                
                $seedParams = ['--class' => $currentSeeder];
                if ($connection) {
                    $seedParams['--database'] = $connection;
                }
                $this->call('db:seed', $seedParams);
            } elseif ($currentSeeder) {
                $this->error("Seeder class [{$currentSeeder}] not found.");
                return 1;
            } else {
                $this->warn("No seeder class found or specified for table [{$table}]. Only truncation/deletion was performed.");
            }
        }

        return 0;
    }

    /**
     * Guess the seeder class name based on the table name.
     *
     * @param string $table
     * @param string|null $connection
     * @return string|null
     */
    protected function guessSeederClass($table, $connection = null)
    {
        $guesses = [
            Str::studly($table) . 'TableSeeder',
            Str::studly(Str::singular($table)) . 'Seeder',
        ];

        foreach ($guesses as $guess) {
            if (class_exists($guess) || class_exists("Database\\Seeders\\{$guess}")) {
                return class_exists($guess) ? $guess : "Database\\Seeders\\{$guess}";
            }
        }

        return null;
    }

    /**
     * Get all tables in the specified database connection.
     *
     * @param string|null $connection
     * @return array
     */
    protected function getAllTables($connection = null)
    {
        $schema = Schema::connection($connection);

        // Laravel 11+ getTableListing()
        if (method_exists($schema, 'getTableListing')) {
            return $schema->getTableListing();
        }

        // Laravel 10.14+ getAllTables()
        if (method_exists($schema, 'getAllTables')) {
            $tables = $schema->getAllTables();
            return array_map(function ($table) {
                return is_object($table) ? reset($table) : $table;
            }, $tables);
        }

        // Fallback to Doctrine Schema Manager
        try {
            if (method_exists($schema, 'getDoctrineSchemaManager')) {
                return $schema->getDoctrineSchemaManager()->listTableNames();
            }
        } catch (\Throwable $e) {
            // Ignore Doctrine dependencies issues
        }

        // Database driver-specific fallbacks
        try {
            $driver = DB::connection($connection)->getDriverName();
            if ($driver === 'sqlite') {
                $results = DB::connection($connection)->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                return array_column($results, 'name');
            }

            if ($driver === 'mysql') {
                $results = DB::connection($connection)->select('SHOW TABLES');
                return array_map(function ($result) {
                    return reset($result);
                }, $results);
            }

            if ($driver === 'pgsql') {
                $results = DB::connection($connection)->select("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname != 'pg_catalog' AND schemaname != 'information_schema'");
                return array_column($results, 'tablename');
            }
        } catch (\Throwable $e) {
            // Fallback empty
        }

        return [];
    }
}
