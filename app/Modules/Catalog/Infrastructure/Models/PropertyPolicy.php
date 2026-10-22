<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Models;

use App\Modules\Catalog\Domain\Policy\PolicyType;
use App\Modules\Platform\Infrastructure\Concerns\BelongsToTenant;
use Database\Factories\PropertyPolicyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Property policies (cancellation, payment, house rules).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $property_id
 * @property PolicyType|string $type
 * @property string $title
 * @property array<string, string>|null $description
 * @property array<string, mixed>|null $structured_terms
 * @property bool $is_active
 */
class PropertyPolicy extends Model
{
    /** @use HasFactory<PropertyPolicyFactory> */
    use HasFactory;

    use BelongsToTenant;

    protected $fillable = [
        'organization_id',
        'property_id',
        'type',
        'title',
        'description',
        'structured_terms',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PolicyType::class,
            'description' => 'array',
            'structured_terms' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
