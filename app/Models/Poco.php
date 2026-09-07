<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PocoSource;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Player-Owned Customs Office, either pulled from ESI (the owning corp's own
 * data) or manually reported by a scout (for a rival corp's office, which ESI
 * has no way to expose).
 *
 * @property int $id
 * @property int|null $office_id
 * @property PocoSource $source
 * @property int|null $map_id
 * @property int $solarsystem_id
 * @property int|null $planet_id
 * @property int|null $corporation_id
 * @property string|null $owner_alias
 * @property int|null $reinforce_exit_start
 * @property int|null $reinforce_exit_end
 * @property string|CarbonImmutable|null $reinforced_until
 * @property string|null $notes
 * @property-read string|CarbonImmutable $created_at
 * @property-read string|CarbonImmutable $updated_at
 * @property-read Solarsystem $solarsystem
 * @property-read Corporation|null $corporation
 * @property-read Celestial|null $planet
 * @property-read Map|null $map
 */
final class Poco extends Model
{
    public function solarsystem(): BelongsTo
    {
        return $this->belongsTo(Solarsystem::class);
    }

    public function corporation(): BelongsTo
    {
        return $this->belongsTo(Corporation::class);
    }

    public function planet(): BelongsTo
    {
        return $this->belongsTo(Celestial::class, 'planet_id');
    }

    public function map(): BelongsTo
    {
        return $this->belongsTo(Map::class);
    }

    protected function casts(): array
    {
        return [
            'office_id' => 'integer',
            'solarsystem_id' => 'integer',
            'planet_id' => 'integer',
            'corporation_id' => 'integer',
            'source' => PocoSource::class,
            'reinforced_until' => 'immutable_datetime',
        ];
    }
}
