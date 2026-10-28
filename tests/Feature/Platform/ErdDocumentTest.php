<?php

declare(strict_types=1);

use App\Modules\Platform\Infrastructure\Documentation\ErdGenerator;

/**
 * The entity relationship diagram of task P0-03.
 *
 * The document is generated from the schema, so it cannot describe a
 * database that does not exist. This test is what keeps it true.
 *
 * When it fails: run `php artisan schema:erd`.
 */
it('keeps the ERD document in sync with the schema', function (): void {
    $path = base_path('docs/ERD.md');

    expect(is_file($path))->toBeTrue('docs/ERD.md is missing. Run: php artisan schema:erd');

    $existing = (string) file_get_contents($path);
    $generator = new ErdGenerator;

    expect($generator->normalize($existing))
        ->toContain($generator->generatedBlock());
});

it('draws every table of the application', function (): void {
    $generator = new ErdGenerator;
    $diagram = $generator->diagram();

    foreach ($generator->tables() as $table) {
        expect($diagram)->toContain('    '.$table.' {');
    }
});

it('names the module of every table that has a model', function (): void {
    $generator = new ErdGenerator;
    $list = $generator->tableList();

    expect($list)->toContain('| `organizations` | platform |')
        ->and($list)->toContain('| `properties` | catalog |')
        ->and($list)->toContain('| `inventory_days` | inventory |');
});
