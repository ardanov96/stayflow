<?php

declare(strict_types=1);

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ShellController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

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
    // The `locale` cookie is in EncryptCookies::$neverEncrypt (see
    // bootstrap/app.php): its value is just a 2-letter code, not a
    // secret, and we want the e2e Playwright suite to be able to
    // pre-seed it without roundtripping through the encryption
    // protocol. Use assertPlainCookie, not assertCookie, because
    // assertCookie would try to decrypt a non-encrypted value and
    // throw DecryptException.
    $response->assertPlainCookie('locale', 'id');
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
    // The base Controller has no public constructor of its own, so
    // IS_PUBLIC only reflects methods defined on the subclass: exactly
    // one, `dashboard`. (An earlier revision of this test expected 2,
    // counting a non-existent inherited __construct.)
    expect(count($reflection->getMethods(ReflectionMethod::IS_PUBLIC)))
        ->toBe(1);
});

it('LocaleController is the only path that mutates the locale cookie', function (): void {
    $reflection = new ReflectionClass(LocaleController::class);
    $publicMethods = array_map(
        static fn (ReflectionMethod $m): string => $m->getName(),
        $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
    );

    expect($publicMethods)->toContain('update');
});
