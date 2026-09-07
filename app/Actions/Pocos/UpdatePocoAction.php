<?php

declare(strict_types=1);

namespace App\Actions\Pocos;

use App\Data\PocoData;
use App\Models\Poco;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class UpdatePocoAction
{
    /**
     * PocoData only exposes owner_alias/reinforce window/reinforced_until/notes -
     * source, office_id, corporation_id and solarsystem_id are never part of an
     * update payload, so an ESI-sourced office's structural facts can't be
     * overwritten here regardless of who's submitting the request.
     *
     * @throws Throwable
     */
    public function handle(Poco $poco, PocoData $data): Poco
    {
        return DB::transaction(function () use ($poco, $data): Poco {
            $poco->update($data->toArray());

            return $poco;
        });
    }
}
