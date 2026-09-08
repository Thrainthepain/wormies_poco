<?php

declare(strict_types=1);

namespace App\Console\Commands\Characters;

use App\Console\Commands\AppCommand;
use App\Jobs\Characters\DispatchCharacterStatusEvents;
use App\Jobs\Characters\UpdateCharacterOnlineStatus;
use App\Models\CharacterStatus;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Throwable;

use function sprintf;

final class GetOnlineCharactersCommand extends AppCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:get-online-characters';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks the online status of characters and updates their status in the database';

    /**
     * Execute the console command.
     *
     * @throws Throwable
     */
    public function handle(): void
    {
        $characters = CharacterStatus::query()
            ->hasRequiredScopes()
            ->get();

        if ($characters->isEmpty()) {
            $this->info('No characters with the required scopes to process.');

            return;
        }

        $this->info(sprintf('Dispatching jobs for %d characters...', $characters->count()));

        $jobs = $characters->map(fn (CharacterStatus $characterStatus): UpdateCharacterOnlineStatus => new UpdateCharacterOnlineStatus($characterStatus->id));

        $batch = Bus::batch($jobs->all())
            ->name('Update Character Online Status')
            ->finally(function (Batch $batch): void {
                // Dispatch a job to handle event dispatching after all character updates are complete
                DispatchCharacterStatusEvents::dispatch();
            })
            ->dispatch();

        $this->info(sprintf('Batch dispatched with ID: %s', $batch->id));
        $this->info('Characters will be processed in parallel.');
    }
}
