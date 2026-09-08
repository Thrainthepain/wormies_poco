<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Pocos\DeletePocoAction;
use App\Actions\Pocos\StoreManualPocoAction;
use App\Actions\Pocos\UpdatePocoAction;
use App\Data\NewPocoData;
use App\Data\PocoData;
use App\Enums\PocoSource;
use App\Http\Controllers\Controller;
use App\Http\Resources\PocoResource;
use App\Models\Map;
use App\Models\MapSolarsystem;
use App\Models\Poco;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class PocoController extends Controller
{
    /**
     * @throws Throwable
     */
    public function index(Map $map): JsonResponse
    {
        Gate::authorize('view', $map);

        $pocos = Poco::query()
            ->with(['planet:id,name', 'corporation:id,name,ticker,alliance_id', 'corporation.alliance:id,name'])
            ->where(fn (Builder $query) => $query
                ->where('source', PocoSource::Esi)
                ->orWhere('map_id', $map->id)
            )
            ->get();

        return response()->json([
            'data' => $pocos->toResourceCollection(PocoResource::class),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function store(NewPocoData $data, MapSolarsystem $mapSolarsystem, StoreManualPocoAction $action): JsonResponse
    {
        $poco = $action->handle($mapSolarsystem, $data);

        return response()->json([
            'message' => 'POCO onboarded successfully.',
            'data' => $poco->toResource(PocoResource::class),
        ], status: Response::HTTP_CREATED);
    }

    /**
     * @throws Throwable
     */
    public function update(Poco $poco, PocoData $data, UpdatePocoAction $action): JsonResponse
    {
        $action->handle($poco, $data);

        return response()->json([
            'message' => 'POCO updated successfully.',
            'data' => $poco->refresh()->toResource(PocoResource::class),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function destroy(Poco $poco, DeletePocoAction $action): JsonResponse
    {
        Gate::authorize('delete', $poco);

        $action->handle($poco);

        return response()->json([
            'message' => 'POCO offboarded successfully.',
        ]);
    }
}
