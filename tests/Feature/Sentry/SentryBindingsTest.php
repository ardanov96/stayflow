<?php

declare(strict_types=1);
use Sentry\SentrySdk;
use Sentry\State\HubInterface;

it('the Sentry SDK classes are autoloaded by composer', function (): void {
    expect(class_exists(SentrySdk::class))->toBeTrue()
        ->and(interface_exists(HubInterface::class))->toBeTrue();
});

it('the Sentry hub is bound in the container when the SDK is installed', function (): void {
    // sentry/sentry-laravel 4.x auto-registers via package discovery and
    // binds a hub as the 'sentry' singleton regardless of DSN. We only
    // assert the binding exists, never that the SDK is initialised
    // against a real Sentry project.
    expect(app()->bound('sentry'))->toBeTrue();
});
