<?php

declare(strict_types=1);

namespace App\Actions\Pocos;

use App\Data\NewPocoData;
use App\Enums\PocoSource;
use App\Models\MapSolarsystem;
use App\Models\Poco;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelData\Optional;
use Throwable;

final readonly class StoreManualPocoAction
{
    /**
     * @throws Throwable
     */
    public function handle(MapSolarsystem $mapSolarsystem, NewPocoData $data): Poco
    {
        return DB::transaction(fn (): Poco => Poco::query()->create([
            'source' => PocoSource::Manual,
            'map_id' => $mapSolarsystem->map_id,
            'solarsystem_id' => $mapSolarsystem->solarsystem_id,
            'owner_alias' => $data->owner_alias instanceof Optional ? null : $data->owner_alias,
            'reinforce_exit_start' => $data->reinforce_exit_start instanceof Optional ? null : $data->reinforce_exit_start,
            'reinforce_exit_end' => $data->reinforce_exit_end instanceof Optional ? null : $data->reinforce_exit_end,
            'reinforced_until' => $data->reinforced_until instanceof Optional ? null : $data->reinforced_until,
            'notes' => $data->notes instanceof Optional ? null : $data->notes,
        ]), attempts: 5);
    }
}
