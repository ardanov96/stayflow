<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/**
 * Test-only helpers for the P1-01 tenant-table arch test. Lives
 * under tests/Support/ so the helpers are autoloaded (psr-4
 * Tests namespace mapped to tests directory) but are not
 * themselves test cases.
 */
final class TenantArchScanner
{
    /**
     * Project root, computed from this file location
     * (tests/Support/TenantArchScanner.php -> project root is
     * two directories up).
     */
    public static function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * FQNs of every Eloquent model under the Infrastructure/Models
     * folder of each module. Order is filesystem order; the arch
     * test does not depend on it.
     *
     * @return list<class-string>
     */
    public static function tenantTableModelClasses(): array
    {
        $root = self::projectRoot();
        $rootPrefix = str_replace('\\', '/', $root);
        $glob = glob($rootPrefix.'/app/Modules/*/Infrastructure/Models/*.php');
        $files = $glob ?: [];
        $classes = [];
        foreach ($files as $file) {
            $normalized = str_replace('\\', '/', $file);
            $relative = substr($normalized, strlen($rootPrefix) + 1, -4);
            $relative = str_replace('/', '\\', $relative);
            $relative = preg_replace('/^app\\\\/', '', $relative);
            $classes[] = 'App\\'.$relative;
        }

        return $classes;
    }

    /**
     * Column names on the given table, using sqlite PRAGMA. Returns
     * the names in the table natural order.
     *
     * @return list<string>
     */
    public static function tableColumns(string $table): array
    {
        $rows = DB::select('PRAGMA table_info("'.$table.'")');

        return array_map(static fn ($r): string => $r->name, $rows);
    }
}
