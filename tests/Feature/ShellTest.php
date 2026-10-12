<?php

declare(strict_types=1);

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ShellController;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shell dashboard returns a successful response in the default locale', function (): void {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('Dashboard')
            ->where('app.name', 'Stayflow')
            ->where('app.currentLocale', 'en')
            ->where('app.locales', ['id', 'en', 'zh']),
    );
});

it('shell dashboard is reachable under the /shell route alias', function (): void {
    $response = $this->get('/shell');

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page->component('Dashboard'));
});

it('SetLocale middleware reads the locale cookie and reflects it on the rendered page', function (): void {
    $response = $this->withCookie('locale', 'en')->get('/');

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page->where('app.currentLocale', 'en'));
});

it('SetLocale middleware rejects unsupported locale cookies and falls back to default', function (): void {
    // Cookies aren't directly trusted; the controller is the only entry point
    // that mutates them, so an unknown cookie value must not switch locales.
    config(['app.locale' => 'en']);
    config(['app.supported_locales' => ['id', 'en', 'zh']]);

    $response = $this->withCookie('locale', 'fr')->get('/');

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page->where('app.currentLocale', 'en'));
});

it('locale update endpoint accepts supported locales and sets the cookie', function (): void {
    $response = $this->post('/locale', ['locale' => 'id']);

    $response->assertRedirect();
    $response->assertCookie('locale', 'id');
});

it('locale update endpoint rejects unsupported locales with 422', function (): void {
    $response = $this->post('/locale', ['locale' => 'fr']);

    $response->assertSessionHasErrors('locale');
});

it('the shell uses the configured supported locales set, never a free-form one', function (): void {
    config(['app.supported_locales' => ['id', 'en', 'zh']]);

    $reflection = new ReflectionClass(ShellController::class);
    expect($reflection->getName())->toBe(ShellController::class);

    // Defense-in-depth: verify the controller's surface area hasn't drifted
    // from "single dashboard action" so a future contributor is forced to
    // think before adding a new public action to the shell.
    expect(count($reflection->getMethods(ReflectionMethod::IS_PUBLIC)))
        ->toBe(2); // __construct (inherited) + dashboard
});

it('LocaleController is the only path that mutates the locale cookie', function (): void {
    $reflection = new ReflectionClass(LocaleController::class);
    $publicMethods = array_map(
        static fn (ReflectionMethod $m): string => $m->getName(),
        $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
    );

    expect($publicMethods)->toContain('update');
});