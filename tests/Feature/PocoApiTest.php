<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\PocoSource;
use App\Models\Character;
use App\Models\Corporation;
use App\Models\Map;
use App\Models\MapAccess;
use App\Models\Poco;
use App\Models\User;

use function Pest\Laravel\actingAs;

it('lists pocos visible on a map', function () {
    $map = Map::factory()->create();
    $user = User::factory()->ownsMap($map)->create();
    $system = placeMapSolarsystem($map, 30013001);

    Poco::query()->create([
        'source' => PocoSource::Manual,
        'map_id' => $map->id,
        'solarsystem_id' => $system->solarsystem_id,
        'owner_alias' => 'Target Corp',
    ]);

    actingAs($user);

    $this->getJson(route('api.maps.pocos.index', $map))
        ->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'source', 'solarsystem_id', 'owner_alias'],
            ],
        ])
        ->assertJsonFragment(['owner_alias' => 'Target Corp']);
});

it('onboards a manually reported poco via the api', function () {
    $map = Map::factory()->create();
    $user = User::factory()->ownsMap($map)->create();
    $system = placeMapSolarsystem($map, 30013002);

    actingAs($user);

    $this->postJson(route('api.map-solarsystems.pocos.store', $system), [
        'owner_alias' => 'Onboarded Target',
        'reinforce_exit_start' => 18,
        'reinforce_exit_end' => 22,
    ])
        ->assertCreated()
        ->assertJsonPath('data.owner_alias', 'Onboarded Target')
        ->assertJsonPath('data.source', PocoSource::Manual->value);

    expect(Poco::query()->where('owner_alias', 'Onboarded Target')->exists())->toBeTrue();
});

it('rejects onboarding from a user without map write access', function () {
    $map = Map::factory()->create();
    $system = placeMapSolarsystem($map, 30013003);
    $viewer = User::factory()
        ->has(Character::factory()->has(MapAccess::factory(['permission' => Permission::Viewer])->for($map)))
        ->create();
    $viewer->forceFill(['preferred_character_id' => $viewer->characters()->value('id')])->save();

    actingAs($viewer->refresh());

    $this->postJson(route('api.map-solarsystems.pocos.store', $system), [
        'owner_alias' => 'Should Not Exist',
    ])->assertForbidden();
});

it('updates a target via the api', function () {
    $map = Map::factory()->create();
    $user = User::factory()->ownsMap($map)->create();
    $system = placeMapSolarsystem($map, 30013004);

    $poco = Poco::query()->create([
        'source' => PocoSource::Manual,
        'map_id' => $map->id,
        'solarsystem_id' => $system->solarsystem_id,
        'owner_alias' => 'Original',
    ]);

    actingAs($user);

    $this->putJson(route('api.pocos.update', $poco), [
        'notes' => 'Now reinforced',
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.notes', 'Now reinforced')
        ->assertJsonPath('data.owner_alias', 'Original');
});

it('offboards a target via the api', function () {
    $map = Map::factory()->create();
    $user = User::factory()->ownsMap($map)->create();
    $system = placeMapSolarsystem($map, 30013005);

    $poco = Poco::query()->create([
        'source' => PocoSource::Manual,
        'map_id' => $map->id,
        'solarsystem_id' => $system->solarsystem_id,
    ]);

    actingAs($user);

    $this->deleteJson(route('api.pocos.destroy', $poco))->assertSuccessful();

    expect(Poco::query()->find($poco->id))->toBeNull();
});

it('never allows offboarding an esi-sourced poco via the api', function () {
    $map = Map::factory()->create();
    $user = User::factory()->ownsMap($map)->create();
    makeSolarsystem(30013006);
    $corporation = Corporation::factory()->create();

    $poco = Poco::query()->create([
        'source' => PocoSource::Esi,
        'office_id' => 90000010,
        'solarsystem_id' => 30013006,
        'corporation_id' => $corporation->id,
    ]);

    actingAs($user);

    $this->deleteJson(route('api.pocos.destroy', $poco))->assertForbidden();

    expect(Poco::query()->find($poco->id))->not->toBeNull();
});
