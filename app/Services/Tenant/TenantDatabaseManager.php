<?php

namespace App\Services\Tenant;

use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TenantDatabaseManager
{
    /**
     * Create a new database schema for the tenant.
     *
     * For MySQL: Creates a new database with the naming convention kasamahr_tenant_{slug}
     * For SQLite: Creates a new SQLite file for the tenant
     */
    public function createSchema(Tenant $tenant): void
    {
        $driver = config('database.default');

        if ($driver === 'sqlite') {
            $this->createSqliteSchema($tenant);
        } else {
            $this->createMysqlSchema($tenant);
        }
    }

    /**
     * Switch the tenant database connection to target the specified tenant's schema.
     *
     * Uses Config::set to update the database name, then purges and reconnects
     * the tenant connection to ensure the new configuration takes effect.
     */
    public function switchConnection(Tenant $tenant): void
    {
        $driver = config('database.default');

        if ($driver === 'sqlite') {
            $databasePath = $this->getSqliteDatabasePath($tenant);
            Config::set('database.connections.tenant.database', $databasePath);
        } else {
            $databaseName = $tenant->getDatabaseName();
            Config::set('database.connections.tenant.database', $databaseName);
        }

        // Purge and reconnect to apply the new configuration
        DB::purge('tenant');
        DB::reconnect('tenant');
    }

    /**
     * Run tenant-specific migrations on the tenant's schema.
     *
     * Executes migrations from the database/migrations/tenant/ directory
     * using the tenant database connection.
     */
    public function migrateSchema(Tenant $tenant): void
    {
        // Ensure connection is switched first
        $this->switchConnection($tenant);

        // Run migrations for tenant-specific tables
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);
    }

    /**
     * Check if a database schema exists for the tenant.
     */
    public function schemaExists(Tenant $tenant): bool
    {
        $driver = config('database.default');

        if ($driver === 'sqlite') {
            return $this->sqliteSchemaExists($tenant);
        }

        return $this->mysqlSchemaExists($tenant);
    }

    /**
     * Create a MySQL database for the tenant.
     */
    protected function createMysqlSchema(Tenant $tenant): void
    {
        $databaseName = $tenant->getDatabaseName();
        $charset = config('database.connections.mysql.charset', 'utf8mb4');
        $collation = config('database.connections.mysql.collation', 'utf8mb4_unicode_ci');

        try {
            // Use the platform connection to execute DDL
            DB::connection('platform')->statement(
                "CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET {$charset} COLLATE {$collation}"
            );
        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to create tenant database '{$databaseName}': ".$e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Create a SQLite database file for the tenant.
     */
    protected function createSqliteSchema(Tenant $tenant): void
    {
        $databasePath = $this->getSqliteDatabasePath($tenant);

        try {
            // Create the SQLite database file
            if (! file_exists($databasePath)) {
                touch($databasePath);
            }
        } catch (\Exception $e) {
            throw new RuntimeException(
                "Failed to create tenant SQLite database at '{$databasePath}': ".$e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Check if a MySQL database exists for the tenant.
     */
    protected function mysqlSchemaExists(Tenant $tenant): bool
    {
        $databaseName = $tenant->getDatabaseName();

        $result = DB::connection('platform')->select(
            'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?',
            [$databaseName]
        );

        return count($result) > 0;
    }

    /**
     * Check if a SQLite database file exists for the tenant.
     */
    protected function sqliteSchemaExists(Tenant $tenant): bool
    {
        $databasePath = $this->getSqliteDatabasePath($tenant);

        return file_exists($databasePath);
    }

    /**
     * Get the SQLite database file path for a tenant.
     */
    protected function getSqliteDatabasePath(Tenant $tenant): string
    {
        return database_path("tenant_{$tenant->slug}.sqlite");
    }
}
