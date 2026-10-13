<?php

declare(strict_types=1);

use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Logging\TenantLogProcessor;
use Monolog\Level;
use Monolog\LogRecord;

beforeEach(function (): void {
    $this->processor = app(TenantLogProcessor::class);
    $this->context = app(OrganizationContext::class);
    $this->baseRecord = new LogRecord(
        datetime: new DateTimeImmutable('2026-10-13T10:00:00+00:00'),
        channel: 'test',
        level: Level::Info,
        message: 'hello',
        context: [],
        extra: [],
    );
});

it('adds organization_id when a context is active', function (): void {
    $this->context->swap(TenantContext::forOrganization(42), function (): void {
        $new = ($this->processor)($this->baseRecord);
        expect($new->extra)->toHaveKey('organization_id')
            ->and($new->extra['organization_id'])->toBe(42);
    });
});

it('adds property_id only when the context has one', function (): void {
    $this->context->swap(TenantContext::forOrganizationAndProperty(42, 7), function (): void {
        $new = ($this->processor)($this->baseRecord);
        expect($new->extra)->toHaveKey('organization_id')
            ->and($new->extra['organization_id'])->toBe(42)
            ->and($new->extra)->toHaveKey('property_id')
            ->and($new->extra['property_id'])->toBe(7);
    });
});

it('does not add property_id when the context is organization-only', function (): void {
    $this->context->swap(TenantContext::forOrganization(42), function (): void {
        $new = ($this->processor)($this->baseRecord);
        expect($new->extra)->not->toHaveKey('property_id');
    });
});

it('returns the record unchanged when no context is active', function (): void {
    $new = ($this->processor)($this->baseRecord);
    expect($new->extra)->toBe([])
        ->and($new->message)->toBe('hello');
});

it('preserves existing extra keys', function (): void {
    $record = $this->baseRecord->with(extra: ['request_id' => 'abc123']);
    $this->context->swap(TenantContext::forOrganization(42), function () use ($record): void {
        $new = ($this->processor)($record);
        expect($new->extra)->toHaveKey('request_id')
            ->and($new->extra['request_id'])->toBe('abc123')
            ->and($new->extra['organization_id'])->toBe(42);
    });
});
