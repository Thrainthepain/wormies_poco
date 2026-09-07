<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Pocos\DeletePocoAction;
use App\Actions\Pocos\StoreManualPocoAction;
use App\Actions\Pocos\UpdatePocoAction;
use App\Data\NewPocoData;
use App\Data\PocoData;
use App\Models\MapSolarsystem;
use App\Models\Poco;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Throwable;

final class PocoController extends Controller
{
    /**
     * @throws Throwable
     */
    public function store(NewPocoData $data, MapSolarsystem $mapSolarsystem, StoreManualPocoAction $storeManualPocoAction): RedirectResponse
    {
        $storeManualPocoAction->handle($mapSolarsystem, $data);

        return back()->notify('POCO reported successfully.', message: 'You have successfully reported a customs office.');
    }

    /**
     * @throws Throwable
     */
    public function update(Poco $poco, PocoData $data, UpdatePocoAction $updatePocoAction): RedirectResponse
    {
        $updatePocoAction->handle($poco, $data);

        return back()->notify('POCO updated successfully.', message: 'You have successfully updated the customs office.');
    }

    /**
     * @throws Throwable
     */
    public function destroy(Poco $poco, DeletePocoAction $deletePocoAction): RedirectResponse
    {
        Gate::authorize('delete', $poco);

        $deletePocoAction->handle($poco);

        return back()->notify('POCO removed successfully.', message: 'You have successfully removed the customs office report.');
    }
}
