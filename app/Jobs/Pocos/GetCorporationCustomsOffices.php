<?php

declare(strict_types=1);

namespace App\Jobs\Pocos;

use App\Builders\EsiTokenBuilder;
use App\Enums\PocoSource;
use App\Models\Character;
use App\Models\Poco;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use NicolasKion\Esi\Connector;
use NicolasKion\Esi\Enums\EsiScope;
use NicolasKion\Esi\Requests\GetCorporationCustomsOfficesRequest;

final class GetCorporationCustomsOffices implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $corporation_id) {}

    public function handle(): void
    {
        $candidates = Character::query()
            ->where('corporation_id', $this->corporation_id)
            ->whereHas('esiTokens', function (Builder $query): void {
                assert($query instanceof EsiTokenBuilder);
                $query->hasCustomsOfficeScopes();
            })
            ->get();

        $result = null;

        foreach ($candidates as $character) {
            $token = $character->getEsiTokenWithScope(EsiScope::ReadCustomsOffices);
            if ($token === null) {
                continue;
            }

            $connector = new Connector($token);
            $result = $connector->sendPaginated(new GetCorporationCustomsOfficesRequest($this->corporation_id));

            if (! $result->failed()) {
                break;
            }
        }

        if ($result === null || $result->failed()) {
            Log::info(sprintf('Failed to fetch customs offices for corporation %d.', $this->corporation_id));

            return;
        }

        $offices = collect($result->data);

        foreach ($offices as $office) {
            try {
                Poco::query()->updateOrCreate(
                    ['office_id' => $office->office_id],
                    [
                        'source' => PocoSource::Esi,
                        'solarsystem_id' => $office->system_id,
                        'corporation_id' => $this->corporation_id,
                        'reinforce_exit_start' => $office->reinforce_exit_start,
                        'reinforce_exit_end' => $office->reinforce_exit_end,
                        'map_id' => null,
                    ]
                );
            } catch (Exception $e) {
                Log::info(sprintf('Failed to update customs office %d: %s', $office->office_id, $e->getMessage()));
            }
        }

        // Scoped to this corporation's own ESI-sourced rows only - never touches
        // manually-reported (rival corp) rows or another corporation's offices.
        Poco::query()
            ->where('source', PocoSource::Esi)
            ->where('corporation_id', $this->corporation_id)
            ->whereNotIn('office_id', $offices->pluck('office_id'))
            ->delete();
    }
}
