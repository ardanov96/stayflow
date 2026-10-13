<?php

declare(strict_types=1);

use App\Modules\Platform\Infrastructure\Concerns\BelongsToTenant;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Tests\Support\TenantArchScanner;

it('every tenant table uses BelongsToTenant and every non-tenant model is on the exemption list', function (string $modelClass): void {
    /** @var Model $instance */
    $instance = new $modelClass;
    $table = $instance->getTable();
    $columns = TenantArchScanner::tableColumns($table);
    $hasOrgId = in_array('organization_id', $columns, true);

    if ($hasOrgId) {
        $traits = array_values(class_uses_recursive($modelClass));
        expect($traits)->toContain(BelongsToTenant::class);

        return;
    }

    // No organization_id column: the model must be on the explicit
    // exemption list. Today the only exemption is Organization itself
    // (it IS the tenant; per ADR-0003 the table is the root of the
    // hierarchy and does not need to belong to one). When a new
    // non-tenant model is added, extend the match expression below
    // with a one-line justification.
    $exempt = match (true) {
        $modelClass === Organization::class => true,
        default => false,
    };

    expect($exempt)->toBeTrue("{$modelClass} uses table `{$table}` which has no organization_id column but is not on the exemption list");
})->with(TenantArchScanner::tenantTableModelClasses());
