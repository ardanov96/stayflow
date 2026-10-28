<?php

declare(strict_types=1);

use App\Modules\Platform\Application\Notifications\Notification;
use App\Modules\Platform\Application\Notifications\NotificationDispatcher;
use App\Modules\Platform\Contracts\NotificationDeliveryLog;
use App\Modules\Platform\Infrastructure\Models\Organization;
use App\Modules\Platform\Infrastructure\Notifications\VerifyEmailMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

/**
 * The notification framework (task P1-15).
 */
uses(RefreshDatabase::class);

it('renders the template in the locale of the notification', function (string $locale): void {
    $mailable = app()->make(VerifyEmailMail::class, [
        'verifyUrl' => 'https://example.test/verify',
        'userName' => 'Wayan',
    ]);

    $mailable->locale($locale);

    $rendered = $mailable->render();

    expect($rendered)->toContain(trans('email.greeting', ['name' => 'Wayan'], $locale))
        ->and($rendered)->toContain(trans('email.verify_body', [], $locale))
        ->and($rendered)->toContain('lang="'.$locale.'"');
})->with(['id', 'en', 'zh']);

it('renders a different template in every locale', function (): void {
    $render = function (string $locale): string {
        $mailable = app()->make(VerifyEmailMail::class, [
            'verifyUrl' => 'https://example.test/verify',
            'userName' => 'Wayan',
        ]);

        $mailable->locale($locale);

        return $mailable->render();
    };

    expect($render('id'))->not->toBe($render('en'))
        ->and($render('en'))->not->toBe($render('zh'));
});

it('records a delivery that worked', function (): void {
    // The mailables are queued, so the job is what is asserted.
    Queue::fake();

    $organization = Organization::factory()->create();

    $deliveries = app(NotificationDispatcher::class)->dispatch(new Notification(
        organizationId: $organization->id,
        propertyId: null,
        channel: 'mail',
        mailable: VerifyEmailMail::class,
        locale: 'id',
        arguments: ['verifyUrl' => 'https://example.test/verify', 'userName' => 'Wayan'],
        recipients: ['guest@example.test'],
    ));

    expect($deliveries)->toHaveCount(1)
        ->and($deliveries[0]->status)->toBe('sent')
        ->and($deliveries[0]->recipient)->toBe('guest@example.test')
        ->and($deliveries[0]->locale)->toBe('id')
        ->and($deliveries[0]->channel)->toBe('mail');

    Queue::assertPushed(SendQueuedMailable::class, 1);
});

it('records a delivery that failed and makes it visible', function (): void {
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('the mail server refused the connection'));

    $organization = Organization::factory()->create();

    $deliveries = app(NotificationDispatcher::class)->dispatch(new Notification(
        organizationId: $organization->id,
        propertyId: null,
        channel: 'mail',
        mailable: VerifyEmailMail::class,
        locale: 'en',
        arguments: ['verifyUrl' => 'https://example.test/verify', 'userName' => 'Wayan'],
        recipients: ['guest@example.test'],
    ));

    expect($deliveries)->toHaveCount(1)
        ->and($deliveries[0]->status)->toBe('failed')
        ->and($deliveries[0]->error)->toContain('the mail server refused');

    $failed = app(NotificationDeliveryLog::class)->failedFor($organization->id);

    expect($failed)->toHaveCount(1)
        ->and($failed[0]->recipient)->toBe('guest@example.test')
        ->and($failed[0]->status)->toBe('failed');
});

it('marks a delivery as bounced when the provider says so', function (): void {
    config(['services.postmark.webhook_token' => 'shared-secret']);

    $organization = Organization::factory()->create();

    app(NotificationDeliveryLog::class)->record(
        organizationId: $organization->id,
        propertyId: null,
        channel: 'mail',
        template: VerifyEmailMail::class,
        locale: 'id',
        recipient: 'guest@example.test',
        sent: true,
        providerMessageId: 'pm-message-1',
    );

    $response = $this->postJson('/webhooks/postmark/shared-secret', [
        'Type' => 'Bounce',
        'MessageID' => 'pm-message-1',
        'Recipient' => 'guest@example.test',
    ]);

    $response->assertOk()->assertJsonPath('status', 'recorded');

    $failed = app(NotificationDeliveryLog::class)->failedFor($organization->id);

    expect($failed)->toHaveCount(1)
        ->and($failed[0]->status)->toBe('bounced');
});

it('refuses a webhook with the wrong secret', function (): void {
    config(['services.postmark.webhook_token' => 'shared-secret']);

    $this->postJson('/webhooks/postmark/wrong-secret', [
        'Type' => 'Bounce',
        'MessageID' => 'pm-message-1',
        'Recipient' => 'guest@example.test',
    ])->assertForbidden();
});
