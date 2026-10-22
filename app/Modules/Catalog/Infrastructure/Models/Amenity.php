<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Models;

use App\Modules\Platform\Infrastructure\Concerns\BelongsToTenant;
use Database\Factories\AmenityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Amenity representation (e.g. "Private Pool", "High-speed WiFi", "King Bed").
 */
class Amenity extends Model
{
    /** @use HasFactory<AmenityFactory> */
    use HasFactory;

    use BelongsToTenant;

    protected $fillable = [
        'organization_id',
        'property_id',
        'code',
        'name',
        'category',
        'icon',
    ];

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(
            Property::class,
            'property_amenities',
            'amenity_id',
            'property_id'
        );
    }

    public function roomTypes(): BelongsToMany
    {
        return $this->belongsToMany(
            RoomType::class,
            'room_type_amenities',
            'amenity_id',
            'room_type_id'
        );
    }
}
