<?php

declare(strict_types=1);

namespace App\Features;

use App\Enums\PocoSource;
use App\Enums\RemovableCard;
use App\Http\Resources\PocoResource;
use App\Models\Map;
use App\Models\Poco;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Inertia\Inertia;
use Inertia\ProvidesInertiaProperties;
use Inertia\RenderContext;
use Throwable;

final readonly class MapPocosFeature implements ProvidesInertiaProperties
{
    /**
     * @param  string[]  $hiddenCards
     */
    public function __construct(
        private Map $map,
        private array $hiddenCards = [],
    ) {}

    public function toInertiaProperties(RenderContext $context): array
    {
        if (in_array(RemovableCard::Pocos->value, $this->hiddenCards)) {
            return [];
        }

        return [
            'map_pocos' => Inertia::defer($this->getPocos(...)),
        ];
    }

    /**
     * @throws Throwable
     */
    private function getPocos(): ResourceCollection
    {
        // Every ESI-sourced office is visible on every map that reaches its
        // system (it's an objective fact, not scouted intel); manually-reported
        // offices are scoped to the map they were reported on, same as
        // Signature.
        return Poco::query()
            ->with(['planet:id,name', 'corporation:id,name,ticker,alliance_id', 'corporation.alliance:id,name'])
            ->where(fn (Builder $query) => $query
                ->where('source', PocoSource::Esi)
                ->orWhere('map_id', $this->map->id)
            )
            ->get()
            ->toResourceCollection(PocoResource::class);
    }
}
