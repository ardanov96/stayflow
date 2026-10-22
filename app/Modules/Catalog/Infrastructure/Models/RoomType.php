<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Models;

use App\Modules\Catalog\Domain\Room\Occupancy;
use App\Modules\Catalog\Domain\Room\RoomTypeStatus;
use App\Modules\Platform\Infrastructure\Concerns\BelongsToTenant;
use Database\Factories\RoomTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Sellable room category (e.g. "Deluxe Ocean King").
 *
 * @property int $id
 * @property int $organization_id
 * @property int $property_id
 * @property string $code
 * @property string $name
 * @property array<string, string>|null $description
 * @property int $base_occupancy
 * @property int $max_adults
 * @property int $max_children
 * @property int $total_units
 * @property RoomTypeStatus $status
 */
class RoomType extends Model
{
    /** @use HasFactory<RoomTypeFactory> */
    use HasFactory;

    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'property_id',
        'code',
        'name',
        'description',
        'base_occupancy',
        'max_adults',
        'max_children',
        'total_units',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'description' => 'array',
            'status' => RoomTypeStatus::class,
            'base_occupancy' => 'integer',
            'max_adults' => 'integer',
            'max_children' => 'integer',
            'total_units' => 'integer',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function roomUnits(): HasMany
    {
        return $this->hasMany(RoomUnit::class);
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(
            Amenity::class,
            'room_type_amenities',
            'room_type_id',
            'amenity_id'
        );
    }

    public function media(): MorphMany
    {
        return $this->morphMany(CatalogMedia::class, 'mediable')->orderBy('sort_order');
    }

    public function occupancy(): Occupancy
    {
        return new Occupancy(
            baseOccupancy: $this->base_occupancy,
            maxAdults: $this->max_adults,
            maxChildren: $this->max_children,
        );
    }
}
