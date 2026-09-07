<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Poco;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Poco
 */
final class PocoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'office_id' => $this->office_id,
            'source' => $this->source,
            'solarsystem_id' => $this->solarsystem_id,
            'corporation_id' => $this->corporation_id,
            'corporation_name' => $this->corporation?->name,
            'corporation_ticker' => $this->corporation?->ticker,
            'alliance_id' => $this->corporation?->alliance_id,
            'alliance_name' => $this->corporation?->alliance?->name,
            'owner_alias' => $this->owner_alias,
            'planet_name' => $this->planet?->name,
            'reinforce_exit_start' => $this->reinforce_exit_start,
            'reinforce_exit_end' => $this->reinforce_exit_end,
            'reinforced_until' => $this->reinforced_until,
            'notes' => $this->notes,
        ];
    }
}
