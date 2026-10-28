<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Documentation;

use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Draws the entity relationship diagram of the application.
 *
 * The diagram is read from the live schema, not from a hand-written list,
 * so it cannot describe a database that does not exist. The types are
 * normalized to a small set of logical types, so the diagram is the same
 * whether it is drawn from SQLite or from MySQL.
 *
 * The tables that belong to a module are found through the Eloquent models
 * of that module; the tables without a model are listed separately, so a
 * reviewer can see what is not covered.
 */
final class ErdGenerator
{
    /**
     * The tables Laravel owns and that carry no product meaning.
     *
     * @var list<string>
     */
    private const FRAMEWORK_TABLES = [
        'migrations',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        // The barrier of the concurrency test harness (P1-10).
        'concurrency_barrier_arrivals',
    ];

    /**
     * The marker of the generated part of the document.
     */
    public const BEGIN_MARKER = '<!-- BEGIN GENERATED -->';

    public const END_MARKER = '<!-- END GENERATED -->';

    /**
     * The whole document, with the generated part replaced.
     */
    public function document(string $existing = ''): string
    {
        $generated = $this->generatedBlock();

        if ($existing === '') {
            return $this->normalize($this->header()."\n".$generated);
        }

        $begin = strpos($existing, self::BEGIN_MARKER);
        $end = strpos($existing, self::END_MARKER);

        if ($begin === false || $end === false || $end < $begin) {
            return $this->normalize($this->header()."\n".$generated);
        }

        return $this->normalize(
            substr($existing, 0, $begin).$generated.substr($existing, $end + strlen(self::END_MARKER)),
        );
    }

    /**
     * The document always uses LF endings, whatever the platform writes.
     */
    public function normalize(string $contents): string
    {
        return rtrim(str_replace("\r\n", "\n", $contents), "\n")."\n";
    }

    /**
     * The part of the document the generator owns.
     *
     * Everything around it is written by a human and is never touched.
     */
    public function generatedBlock(): string
    {
        return self::BEGIN_MARKER."\n".$this->diagram()."\n".$this->tableList()."\n".self::END_MARKER;
    }

    /**
     * The Mermaid diagram of the schema.
     */
    public function diagram(): string
    {
        $lines = ['```mermaid', 'erDiagram'];

        foreach ($this->tables() as $table) {
            $lines[] = '    '.$table.' {';

            foreach ($this->columns($table) as $column) {
                $lines[] = '        '.$this->logicalType($column).' '.$column['name'].$this->keySuffix($table, $column);
            }

            $lines[] = '    }';
        }

        foreach ($this->foreignKeys() as $foreignKey) {
            $lines[] = sprintf(
                '    %s ||--o{ %s : "%s"',
                $foreignKey['foreign_table'],
                $foreignKey['table'],
                // The local column, not the constraint name: SQLite does
                // not name its foreign keys, and the diagram has to be
                // the same on every driver.
                implode(', ', $foreignKey['columns']),
            );
        }

        $lines[] = '```';

        return implode("\n", $lines);
    }

    /**
     * The list of the tables and the module that owns them.
     */
    public function tableList(): string
    {
        $modules = $this->tableModules();
        $tables = $this->tables();

        $lines = ['## Tables', '', '| Table | Module |', '|---|---|'];

        $unmapped = [];

        foreach ($tables as $table) {
            $module = $modules[$table] ?? null;

            if ($module === null) {
                $unmapped[] = $table;
                $lines[] = '| `'.$table.'` | *(no model)* |';

                continue;
            }

            $lines[] = '| `'.$table.'` | '.$module.' |';
        }

        if ($unmapped !== []) {
            $lines[] = '';
            $lines[] = 'The tables marked *(no model)* have no Eloquent model yet: '
                .implode(', ', array_map(static fn (string $t): string => '`'.$t.'`', $unmapped)).'.';
        }

        return implode("\n", $lines);
    }

    /**
     * The tables of the application, in a stable order.
     *
     * @return list<string>
     */
    public function tables(): array
    {
        $tables = [];

        foreach (Schema::getTables() as $table) {
            $name = (string) $table['name'];

            if ($name === '' || in_array($name, self::FRAMEWORK_TABLES, true)) {
                continue;
            }

            $tables[] = $name;
        }

        sort($tables);

        return $tables;
    }

    /**
     * The foreign keys of the application.
     *
     * @return list<array{table: string, foreign_table: string, name: string, columns: list<string>}>
     */
    public function foreignKeys(): array
    {
        $keys = [];

        foreach ($this->tables() as $table) {
            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                $keys[] = [
                    'table' => $table,
                    'foreign_table' => (string) ($foreignKey['foreign_table'] ?? ''),
                    'name' => (string) ($foreignKey['name'] ?? ''),
                    'columns' => array_values(array_map('strval', (array) ($foreignKey['columns'] ?? []))),
                ];
            }
        }

        return $keys;
    }

    /**
     * The columns of a table.
     *
     * @return list<array{name: string, type: string, nullable: bool}>
     */
    private function columns(string $table): array
    {
        $columns = [];

        foreach (Schema::getColumns($table) as $column) {
            $columns[] = [
                'name' => (string) $column['name'],
                'type' => (string) $column['type_name'],
                'nullable' => (bool) $column['nullable'],
            ];
        }

        return $columns;
    }

    /**
     * The primary key columns of a table.
     *
     * @return list<string>
     */
    private function primaryKeyColumns(string $table): array
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['primary'] === true) {
                return array_map('strval', $index['columns']);
            }
        }

        return [];
    }

    /**
     * The columns of a table that a unique index covers.
     *
     * @return list<string>
     */
    private function uniqueColumns(string $table): array
    {
        $unique = [];

        foreach (Schema::getIndexes($table) as $index) {
            if ($index['unique'] !== true) {
                continue;
            }

            foreach ($index['columns'] as $column) {
                $unique[] = (string) $column;
            }
        }

        return array_values(array_unique($unique));
    }

    /**
     * The columns of a table that a foreign key covers.
     *
     * @return list<string>
     */
    private function foreignKeyColumns(string $table): array
    {
        $columns = [];

        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            foreach ((array) ($foreignKey['columns'] ?? []) as $column) {
                $columns[] = (string) $column;
            }
        }

        return array_values(array_unique($columns));
    }

    /**
     * The module that owns each table, read from the Eloquent models.
     *
     * @return array<string, string>
     */
    private function tableModules(): array
    {
        $modules = [];
        $path = base_path('app/Modules');

        if (! is_dir($path)) {
            return $modules;
        }

        foreach (glob($path.'/*/Infrastructure/Models/*.php') ?: [] as $file) {
            $class = $this->modelClass($file);

            if ($class === null) {
                continue;
            }

            try {
                $model = new $class;
                $table = $model->getTable();
            } catch (Throwable) {
                continue;
            }

            $module = $this->moduleOfClass($class);

            if ($module !== null) {
                $modules[$table] = $module;
            }
        }

        ksort($modules);

        return $modules;
    }

    private function modelClass(string $file): ?string
    {
        $contents = (string) file_get_contents($file);

        if (preg_match('/namespace\s+([^;]+);/', $contents, $namespace) !== 1) {
            return null;
        }

        if (preg_match('/class\s+(\w+)/', $contents, $class) !== 1) {
            return null;
        }

        return trim($namespace[1]).'\\'.$class[1];
    }

    private function moduleOfClass(string $class): ?string
    {
        if (preg_match('/^App\\\\Modules\\\\(\w+)\\\\/', $class, $matches) !== 1) {
            return null;
        }

        return strtolower($matches[1]);
    }

    /**
     * The logical type of a column.
     *
     * @param  array{name: string, type: string, nullable: bool}  $column
     */
    private function logicalType(array $column): string
    {
        if (str_ends_with($column['name'], '_id')) {
            return 'ref';
        }

        $type = strtolower($column['type']);

        if (str_contains($type, 'bool') || $type === 'tinyint(1)') {
            return 'bool';
        }

        if (str_contains($type, 'int')) {
            return 'int';
        }

        if (str_contains($type, 'decimal') || str_contains($type, 'numeric') || str_contains($type, 'float') || str_contains($type, 'double')) {
            return 'decimal';
        }

        if (str_contains($type, 'text') || str_contains($type, 'json')) {
            return 'text';
        }

        if (str_contains($type, 'uuid')) {
            return 'uuid';
        }

        if (str_contains($type, 'blob') || str_contains($type, 'binary')) {
            return 'blob';
        }

        if (str_contains($type, 'date') && ! str_contains($type, 'time')) {
            return 'date';
        }

        if (str_contains($type, 'time')) {
            return 'datetime';
        }

        return 'string';
    }

    /**
     * The key marker of a column.
     *
     * @param  array{name: string, type: string, nullable: bool}  $column
     */
    private function keySuffix(string $table, array $column): string
    {
        if (in_array($column['name'], $this->primaryKeyColumns($table), true)) {
            return ' PK';
        }

        if (in_array($column['name'], $this->foreignKeyColumns($table), true)) {
            return ' FK';
        }

        if (in_array($column['name'], $this->uniqueColumns($table), true)) {
            return ' UK';
        }

        return '';
    }

    private function header(): string
    {
        return implode("\n", [
            '# ERD — entity relationship diagram',
            '',
            'The diagram below is generated from the schema by `php artisan schema:erd`.',
            'It is checked by `tests/Feature/Platform/ErdDocumentTest.php`: when the',
            'schema changes and this document does not, the test suite fails.',
            '',
            'The logical model (the entities, the invariants and the report',
            'definitions) is in `docs/DOMAIN_MODEL.md`.',
            '',
            'The types are logical, not physical: `id` and `ref` are the keys, `text`',
            'covers the JSON payloads, and the diagram is the same whether it was',
            'drawn from SQLite or from MySQL.',
            '',
        ]);
    }
}
