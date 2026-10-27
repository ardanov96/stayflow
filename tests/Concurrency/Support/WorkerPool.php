<?php

declare(strict_types=1);

namespace Tests\Concurrency\Support;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Runs a set of jobs in independent worker processes.
 *
 * Each worker is a separate PHP process with its own MySQL session, so the
 * database sees genuinely concurrent transactions. The parent only starts
 * the processes and collects their results.
 */
final class WorkerPool
{
    /**
     * Run the jobs and wait for all of them.
     *
     * @param  list<array<string, mixed>>  $jobs
     * @return list<WorkerResult>
     */
    public function run(array $jobs, int $timeoutSeconds = 300): array
    {
        if ($jobs === []) {
            return [];
        }

        $root = base_path();
        $workerScript = $root.'/tests/Concurrency/Support/worker.php';
        $directory = storage_path('framework/testing/concurrency');

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $processes = [];
        $files = [];

        foreach ($jobs as $index => $job) {
            $worker = sprintf('%03d-%s', $index, (string) ($job['worker'] ?? 'worker'));
            $inputPath = sprintf('%s/%s.in.json', $directory, $worker);
            $outputPath = sprintf('%s/%s.out.json', $directory, $worker);

            file_put_contents(
                $inputPath,
                json_encode($job, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            );

            $files[$worker] = [$inputPath, $outputPath];

            $process = new Process(
                [PHP_BINARY, $workerScript, $inputPath, $outputPath],
                $root,
                null,
                null,
                $timeoutSeconds,
            );

            $process->start();

            $processes[$worker] = $process;
        }

        $results = [];

        foreach ($processes as $worker => $process) {
            [$inputPath, $outputPath] = $files[$worker];

            try {
                $process->wait();
            } catch (ProcessTimedOutException $e) {
                $process->stop(1);

                $results[] = new WorkerResult(
                    worker: $worker,
                    ok: false,
                    errorClass: ProcessTimedOutException::class,
                    errorMessage: $e->getMessage(),
                    stderr: $process->getErrorOutput(),
                );

                @unlink($inputPath);
                @unlink($outputPath);

                continue;
            }

            $results[] = $this->readResult($worker, $outputPath, $process);

            @unlink($inputPath);
            @unlink($outputPath);
        }

        return $results;
    }

    private function readResult(string $worker, string $outputPath, Process $process): WorkerResult
    {
        $stderr = $process->getErrorOutput();

        if (! is_file($outputPath)) {
            return new WorkerResult(
                worker: $worker,
                ok: false,
                errorClass: 'WorkerDidNotReport',
                errorMessage: 'The worker exited without writing a result file.',
                stderr: $stderr,
            );
        }

        $raw = (string) file_get_contents($outputPath);

        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            return new WorkerResult(
                worker: $worker,
                ok: false,
                errorClass: 'WorkerResultUnreadable',
                errorMessage: $e->getMessage(),
                stderr: $stderr,
            );
        }

        $ok = ($payload['status'] ?? null) === 'ok';

        /** @var array{class?: string, message?: string} $error */
        $error = is_array($payload['error'] ?? null) ? $payload['error'] : [];

        return new WorkerResult(
            worker: $worker,
            ok: $ok,
            value: $payload['result'] ?? null,
            errorClass: $error['class'] ?? null,
            errorMessage: $error['message'] ?? null,
            durationMs: (float) ($payload['duration_ms'] ?? 0),
            startedAt: (float) ($payload['started_at'] ?? 0),
            finishedAt: (float) ($payload['finished_at'] ?? 0),
            barrierReleasedAt: (float) ($payload['barrier_released_at'] ?? 0),
            stderr: $stderr,
        );
    }
}
