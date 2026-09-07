<?php

declare(strict_types=1);

namespace App\Console\Commands\Pocos;

use App\Builders\EsiTokenBuilder;
use App\Console\Commands\AppCommand;
use App\Jobs\Pocos\GetCorporationCustomsOffices;
use App\Models\Character;
use Illuminate\Database\Eloquent\Builder;

final class GetCorporationCustomsOfficesCommand extends AppCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:get-corporation-customs-offices-command {--sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pull each tracked corporation\'s own customs offices from ESI';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sync = $this->option('sync');

        $corporationIds = Character::query()
            ->whereHas('esiTokens', function (Builder $query): void {
                assert($query instanceof EsiTokenBuilder);
                $query->hasCustomsOfficeScopes();
            })
            ->whereNotNull('corporation_id')
            ->distinct()
            ->pluck('corporation_id');

        foreach ($corporationIds as $corporationId) {
            if ($sync) {
                GetCorporationCustomsOffices::dispatchSync($corporationId);

                continue;
            }

            GetCorporationCustomsOffices::dispatch($corporationId);
        }

        return self::SUCCESS;
    }
}
