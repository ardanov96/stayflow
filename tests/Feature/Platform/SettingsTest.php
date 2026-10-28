<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Models\Property;
use App\Modules\Platform\Contracts\OrganizationContext;
use App\Modules\Platform\Contracts\Settings;
use App\Modules\Platform\Domain\Tenancy\TenantContext;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The settings of the organization and of its properties (task P1-13).
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->property = Property::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    $this->settings = app(Settings::class);

    $this->context = app(OrganizationContext::class);
    $this->tenant = TenantContext::forOrganizationAndProperty(
        $this->organization->id,
        $this->property->id,
    );
});

it('returns the default of a setting that was never written', function (): void {
    $this->context->swap($this->tenant, function (): void {
        expect($this->settings->get('currency'))->toBe('IDR')
            ->and($this->settings->getInt('service_charge_percent'))->toBe(0)
            ->and($this->settings->getBool('breakfast_included'))->toBeFalse();
    });
});

it('returns the value that was written', function (): void {
    $this->context->swap($this->tenant, function (): void {
        $this->settings->set('currency', 'USD', $this->property->id);
        $this->settings->set('service_charge_percent', 10, $this->property->id);

        expect($this->settings->get('currency', $this->property->id))->toBe('USD')
            ->and($this->settings->getInt('service_charge_percent', $this->property->id))->toBe(10);
    });
});

it('refuses a value that does not match the type of the setting', function (): void {
    $this->context->swap($this->tenant, function (): void {
        expect(fn () => $this->settings->set('service_charge_percent', 'ten', $this->property->id))
            ->toThrow(InvalidArgumentException::class);

        expect(fn () => $this->settings->set('locale', 'fr'))
            ->toThrow(InvalidArgumentException::class);
    });
});

it('refuses a setting that does not exist', function (): void {
    $this->context->swap($this->tenant, function (): void {
        expect(fn () => $this->settings->get('there_is_no_such_setting'))
            ->toThrow(InvalidArgumentException::class);
    });
});

it('audits the change of a setting', function (): void {
    $this->context->swap($this->tenant, function (): void {
        $this->settings->set('currency', 'USD', $this->property->id);
    });

    $log = DB::table('audit_logs')
        ->where('event', 'settings.updated')
        ->where('organization_id', $this->organization->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and(json_decode((string) $log->new_values, true))->toBe(['value' => 'USD'])
        ->and(json_decode((string) $log->metadata, true))->toBe(['key' => 'currency']);
});

it('keeps the value in the cache and invalidates it when it changes', function (): void {
    $this->context->swap($this->tenant, function (): void {
        // The value is read, and kept.
        expect($this->settings->get('currency', $this->property->id))->toBe('IDR');

        // A change behind the back of the settings is not seen: the value
        // is cached.
        DB::table('settings')->insert([
            'organization_id' => $this->organization->id,
            'property_id' => $this->property->id,
            'key' => 'currency',
            'value' => json_encode('USD'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect($this->settings->get('currency', $this->property->id))->toBe('IDR');

        // A change through the settings invalidates the cache.
        $this->settings->set('currency', 'EUR', $this->property->id);

        expect($this->settings->get('currency', $this->property->id))->toBe('EUR');
    });
});

it('refuses a setting of a property without a property', function (): void {
    $this->context->swap($this->tenant, function (): void {
        expect(fn () => $this->settings->set('currency', 'USD'))
            ->toThrow(InvalidArgumentException::class);
    });
});

it('lists every setting of the scope', function (): void {
    $this->context->swap($this->tenant, function (): void {
        $all = $this->settings->all($this->property->id);

        expect($all)->toHaveKey('currency')
            ->and($all)->toHaveKey('locale')
            ->and($all['currency'])->toBe('IDR');
    });
});
