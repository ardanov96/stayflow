<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Models;

use App\Modules\Catalog\Domain\Property\PropertyStatus;
use App\Modules\Platform\Infrastructure\Concerns\BelongsToTenant;
use App\Modules\Platform\Infrastructure\Models\Organization;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Property model representing a physical hotel, resort, or villa estate.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $slug
 * @property PropertyStatus $status
 * @property string $timezone
 * @property string $currency
 * @property string $check_in_time
 * @property string $check_out_time
 * @property string|null $email
 * @property string|null $phone
 * @property array<string, mixed>|null $address
 * @property array<string, string>|null $description
 */
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory;

    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'status',
        'timezone',
        'currency',
        'check_in_time',
        'check_out_time',
        'email',
        'phone',
        'address',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PropertyStatus::class,
            'address' => 'array',
            'description' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function roomTypes(): HasMany
    {
        return $this->hasMany(RoomType::class);
    }

    public function roomUnits(): HasMany
    {
        return $this->hasMany(RoomUnit::class);
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(
            Amenity::class,
            'property_amenities',
            'property_id',
            'amenity_id'
        );
    }

    public function taxRules(): HasMany
    {
        return $this->hasMany(TaxRule::class)->orderBy('order');
    }

    public function policies(): HasMany
    {
        return $this->hasMany(PropertyPolicy::class);
    }

    public function media(): MorphMany
    {
        return $this->morphMany(CatalogMedia::class, 'mediable')->orderBy('sort_order');
    }
}
