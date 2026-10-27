<?php

declare(strict_types=1);

namespace Tests\Concurrency\Support;

/**
 * The outcome of one worker process.
 */
final readonly class WorkerResult
{
    /**
     * @param  array<string, mixed>|list<mixed>|int|string|float|bool|null  $value
     */
    public function __construct(
        public string $worker,
        public bool $ok,
        public mixed $value = null,
        public ?string $errorClass = null,
        public ?string $errorMessage = null,
        public float $durationMs = 0.0,
        public float $startedAt = 0.0,
        public float $finishedAt = 0.0,
        public float $barrierReleasedAt = 0.0,
        public string $stderr = '',
    ) {}

    /**
     * Whether the worker failed with the given exception class.
     */
    public function failedWith(string $exceptionClass): bool
    {
        return ! $this->ok && $this->errorClass === $exceptionClass;
    }

    /**
     * Whether the worker failed with an exception of the given class or of
     * one of its subclasses.
     */
    public function failedWithInstanceOf(string $exceptionClass): bool
    {
        return ! $this->ok && $this->errorClass !== null && is_a($this->errorClass, $exceptionClass, true);
    }

    /**
     * The outcome of every operation the worker ran.
     *
     * @return list<array{status: string, value?: mixed, error?: array{class: string, message: string}}>
     */
    public function operations(): array
    {
        if (! is_array($this->value)) {
            return [];
        }

        return array_values(array_filter(
            $this->value,
            static fn (mixed $operation): bool => is_array($operation) && isset($operation['status']),
        ));
    }

    /**
     * The operations that succeeded.
     *
     * @return list<array{status: string, value?: mixed, error?: array{class: string, message: string}}>
     */
    public function successfulOperations(): array
    {
        return array_values(array_filter(
            $this->operations(),
            static fn (array $operation): bool => $operation['status'] === 'ok',
        ));
    }

    /**
     * The operations that failed, with the exception class they failed with.
     *
     * @return list<string>
     */
    public function failedOperationClasses(): array
    {
        $classes = [];

        foreach ($this->operations() as $operation) {
            if ($operation['status'] === 'error') {
                $classes[] = (string) ($operation['error']['class'] ?? '');
            }
        }

        return $classes;
    }

    /**
     * The operations that failed, with the exception they failed with.
     *
     * @return list<array{class: string, message: string}>
     */
    public function failedOperations(): array
    {
        $failures = [];

        foreach ($this->operations() as $operation) {
            if ($operation['status'] !== 'error') {
                continue;
            }

            $failures[] = [
                'class' => (string) ($operation['error']['class'] ?? ''),
                'message' => (string) ($operation['error']['message'] ?? ''),
            ];
        }

        return $failures;
    }

    /**
     * A short, readable description used in assertion messages.
     */
    public function describe(): string
    {
        if ($this->ok) {
            return sprintf('%s: ok', $this->worker);
        }

        return sprintf('%s: %s (%s)', $this->worker, $this->errorClass ?? 'error', $this->errorMessage ?? '');
    }
}
