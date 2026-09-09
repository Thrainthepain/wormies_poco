<?php

declare(strict_types=1);

use App\Exception\CharacterNotAuthorizedException;
use App\Models\Character;
use App\Models\EsiScope;
use App\Models\User;
use App\Services\EsiAuthService;
use Illuminate\Support\Facades\Bus;
use Laravel\Socialite\Facades\Socialite;
use NicolasKion\Esi\DTO\CharacterAffiliation;
use NicolasKion\Esi\DTO\EsiResult;
use NicolasKion\Esi\Esi;
use SocialiteProviders\Manager\OAuth2\User as SocialiteUser;

function fakeEveLogin(int $characterId = 999, int $corporationId = 2000, ?int $allianceId = 3000): void
{
    Bus::fake();

    $socialiteUser = new SocialiteUser();
    $socialiteUser->attributes = [
        'character_id' => $characterId,
        'character_name' => 'Test Pilot',
        'character_owner_hash' => 'owner-hash',
    ];
    $socialiteUser->token = 'access-token';
    $socialiteUser->refreshToken = 'refresh-token';
    $socialiteUser->accessTokenResponseBody = ['token_type' => 'Bearer', 'expires_in' => 1200];
    $socialiteUser->user = ['scp' => []];

    Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

    $esi = Mockery::mock(Esi::class);
    $esi->shouldReceive('getAffiliations')->andReturn(new EsiResult(
        data: [new CharacterAffiliation($characterId, $corporationId, $allianceId, null)],
    ));
    app()->instance(Esi::class, $esi);
}

it('allows any character to log in when no whitelist is configured', function () {
    config()->set('access.allowed_affiliation_ids', []);
    fakeEveLogin(characterId: 999, corporationId: 2000, allianceId: 3000);

    [$user, $character] = app(EsiAuthService::class)->getUser();

    expect($user)->toBeInstanceOf(User::class)
        ->and($character->id)->toBe(999);
});

it('allows a character whose affiliation is whitelisted', function (int $allowedId) {
    config()->set('access.allowed_affiliation_ids', [$allowedId]);
    fakeEveLogin(characterId: 999, corporationId: 2000, allianceId: 3000);

    [$user] = app(EsiAuthService::class)->getUser();

    expect($user)->toBeInstanceOf(User::class);
})->with([
    'character id' => 999,
    'corporation id' => 2000,
    'alliance id' => 3000,
]);

it('rejects a character whose affiliations are not whitelisted', function () {
    config()->set('access.allowed_affiliation_ids', [123456]);
    fakeEveLogin(characterId: 999, corporationId: 2000, allianceId: 3000);

    app(EsiAuthService::class)->getUser();
})->throws(CharacterNotAuthorizedException::class);

it('reuses an existing esi token instead of creating a duplicate, and never shrinks its scopes', function () {
    config()->set('access.allowed_affiliation_ids', []);

    EsiScope::query()->create(['name' => 'publicData', 'is_default' => true]);
    EsiScope::query()->create(['name' => 'esi-location.read_location.v1', 'is_default' => false]);

    $character = Character::factory()->create(['id' => 999]);
    $existingToken = $character->esiTokens()->create([
        'access_token' => 'stale-access-token',
        'refresh_token' => 'stale-refresh-token',
        'token_type' => 'character',
        'character_owner_hash' => 'owner-hash',
        'expires_at' => now()->addHour(),
    ]);
    $existingToken->esiScopes()->sync(EsiScope::query()->pluck('id'));

    Bus::fake();

    $socialiteUser = new SocialiteUser();
    $socialiteUser->attributes = [
        'character_id' => 999,
        'character_name' => 'Test Pilot',
        'character_owner_hash' => 'owner-hash',
    ];
    $socialiteUser->token = 'fresh-access-token';
    $socialiteUser->refreshToken = 'fresh-refresh-token';
    $socialiteUser->accessTokenResponseBody = ['token_type' => 'Bearer', 'expires_in' => 1200];
    // This native-SSO login only requests publicData - narrower than what the
    // character already granted via the AllianceAuth bridge.
    $socialiteUser->user = ['scp' => ['publicData']];

    Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

    $esi = Mockery::mock(Esi::class);
    $esi->shouldReceive('getAffiliations')->andReturn(new EsiResult(
        data: [new CharacterAffiliation(999, 2000, 3000, null)],
    ));
    app()->instance(Esi::class, $esi);

    app(EsiAuthService::class)->getUser();

    expect($character->esiTokens()->count())->toBe(1)
        ->and($existingToken->fresh()->access_token)->toBe('fresh-access-token')
        ->and($existingToken->fresh()->esiScopes()->pluck('name')->all())
        ->toContain('publicData', 'esi-location.read_location.v1');
});

it('does not create a user account for a rejected character', function () {
    config()->set('access.allowed_affiliation_ids', [123456]);
    fakeEveLogin(characterId: 999, corporationId: 2000, allianceId: 3000);

    try {
        app(EsiAuthService::class)->getUser();
    } catch (CharacterNotAuthorizedException) {
        // expected
    }

    expect(User::query()->count())->toBe(0);
});
