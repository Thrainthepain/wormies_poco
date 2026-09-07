<?php

declare(strict_types=1);

use App\Actions\Pocos\DeletePocoAction;
use App\Actions\Pocos\StoreManualPocoAction;
use App\Actions\Pocos\UpdatePocoAction;
use App\Data\NewPocoData;
use App\Data\PocoData;
use App\Enums\Permission;
use App\Enums\PocoSource;
use App\Models\Character;
use App\Models\Corporation;
use App\Models\Map;
use App\Models\MapAccess;
use App\Models\Poco;
use App\Models\User;

function pocoTestUser(Map $map, Permission $permission): User
{
    $user = User::factory()
        ->has(Character::factory()->has(MapAccess::factory(['permission' => $permission])->for($map)))
        ->create();

    $user->forceFill(['preferred_character_id' => $user->characters()->value('id')])->save();

    return $user->refresh();
}

it('stores a manually reported poco on a map system', function () {
    $map = Map::factory()->create();
    $system = placeMapSolarsystem($map, 30012001);

    $poco = app(StoreManualPocoAction::class)->handle($system, NewPocoData::from([
        'owner_alias' => 'Some Rival Corp',
        'reinforce_exit_start' => 18,
        'reinforce_exit_end' => 22,
    ]));

    expect($poco->source)->toBe(PocoSource::Manual)
        ->and($poco->map_id)->toBe($map->id)
        ->and($poco->solarsystem_id)->toBe(30012001)
        ->and($poco->owner_alias)->toBe('Some Rival Corp')
        ->and($poco->office_id)->toBeNull();
});

it('updates live-intel fields on an existing poco', function () {
    $map = Map::factory()->create();
    $system = placeMapSolarsystem($map, 30012002);
    $poco = Poco::query()->create([
        'source' => PocoSource::Manual,
        'map_id' => $map->id,
        'solarsystem_id' => $system->solarsystem_id,
        'owner_alias' => 'Original Owner',
    ]);

    app(UpdatePocoAction::class)->handle($poco, PocoData::from(['notes' => 'Now reinforced, watch out']));

    expect($poco->fresh())
        ->owner_alias->toBe('Original Owner')
        ->notes->toBe('Now reinforced, watch out');
});

it('never lets an update payload touch an esi-sourced poco\'s structural fields', function () {
    $corporation = Corporation::factory()->create();
    $poco = Poco::query()->create([
        'source' => PocoSource::Esi,
        'office_id' => 90000001,
        'solarsystem_id' => 30012003,
        'corporation_id' => $corporation->id,
    ]);

    app(UpdatePocoAction::class)->handle($poco, PocoData::from(['notes' => 'Currently vulnerable']));

    expect($poco->fresh())
        ->source->toBe(PocoSource::Esi)
        ->office_id->toBe(90000001)
        ->corporation_id->toBe($corporation->id)
        ->notes->toBe('Currently vulnerable');
});

it('deletes a manually reported poco', function () {
    $map = Map::factory()->create();
    $poco = Poco::query()->create([
        'source' => PocoSource::Manual,
        'map_id' => $map->id,
        'solarsystem_id' => 30012004,
    ]);

    app(DeletePocoAction::class)->handle($poco);

    expect(Poco::query()->find($poco->id))->toBeNull();
});

it('lets a map manager delete a manually reported poco but not a viewer', function () {
    $map = Map::factory()->create();
    $poco = Poco::query()->create([
        'source' => PocoSource::Manual,
        'map_id' => $map->id,
        'solarsystem_id' => 30012005,
    ]);

    $manager = pocoTestUser($map, Permission::Manager);
    $viewer = pocoTestUser($map, Permission::Viewer);

    expect($manager->can('delete', $poco))->toBeTrue()
        ->and($viewer->can('delete', $poco))->toBeFalse();
});

it('never allows deleting an esi-sourced poco regardless of map permission', function () {
    $map = Map::factory()->create();
    $corporation = Corporation::factory()->create();
    $poco = Poco::query()->create([
        'source' => PocoSource::Esi,
        'office_id' => 90000002,
        'solarsystem_id' => 30012006,
        'corporation_id' => $corporation->id,
    ]);

    $manager = pocoTestUser($map, Permission::Manager);

    expect($manager->can('delete', $poco))->toBeFalse();
});

it('allows any signed-in user to add live intel to an esi-sourced poco', function () {
    $map = Map::factory()->create();
    $corporation = Corporation::factory()->create();
    $poco = Poco::query()->create([
        'source' => PocoSource::Esi,
        'office_id' => 90000003,
        'solarsystem_id' => 30012007,
        'corporation_id' => $corporation->id,
    ]);

    $viewer = pocoTestUser($map, Permission::Viewer);

    expect($viewer->can('update', $poco))->toBeTrue();
});
