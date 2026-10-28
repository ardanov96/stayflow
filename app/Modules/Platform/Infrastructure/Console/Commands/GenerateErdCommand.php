<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Console\Commands;

use App\Modules\Platform\Infrastructure\Documentation\ErdGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Draws the entity relationship diagram from the schema.
 *
 * The document is checked by the test suite, so it can never describe a
 * schema that does not exist.
 */
final class GenerateErdCommand extends Command
{
    protected $signature = 'schema:erd
        {--output=docs/ERD.md : The document to write}
        {--database= : The connection to read the schema from (default: the current one)}
        {--check : Only report whether the document is up to date}';

    protected $description = 'Draw the entity relationship diagram from the schema';

    public function handle(): int
    {
        $database = $this->option('database');

        if (is_string($database) && $database !== '') {
            config(['database.default' => $database]);
            DB::purge($database);
        }

        $path = base_path((string) $this->option('output'));
        $existing = is_file($path) ? (string) file_get_contents($path) : '';
        $generator = new ErdGenerator;

        if ($this->option('check')) {
            if (! str_contains($generator->normalize($existing), $generator->generatedBlock())) {
                $this->error($path.' is out of date. Run: php artisan schema:erd');

                return self::FAILURE;
            }

            $this->info($path.' is up to date.');

            return self::SUCCESS;
        }

        if (! is_dir(dirname($path))) {
            throw new RuntimeException('The directory of '.$path.' does not exist.');
        }

        file_put_contents($path, $generator->document($existing));

        $this->info('Wrote '.$path);

        return self::SUCCESS;
    }
}
