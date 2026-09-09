<?php

declare(strict_types=1);

use App\Exception\CharacterNotAuthorizedException;
use App\Models\Character;
use App\Models\EsiScope;
use App\Models\User;
use App\Services\AllianceAuthService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use NicolasKion\Esi\DTO\CharacterAffiliation;
use NicolasKion\Esi\DTO\EsiResult;
use NicolasKion\Esi\Esi;

beforeEach(function () {
    Bus::fake();

    config()->set('services.allianceauth.base_url', 'https://auth.r3v-w.space');
    config()->set('services.allianceauth.client_id', 'test-client-id');
    config()->set('services.allianceauth.client_secret', 'test-client-secret');
    config()->set('services.allianceauth.redirect', 'https://wormhole.r3v-w.space/auth/allianceauth/callback');
    config()->set('access.allowed_affiliation_ids', []);
});

function fakeAllianceAuthEsi(int $characterId = 8888, int $corporationId = 2000, ?int $allianceId = 3000): void
{
    $esi = Mockery::mock(Esi::class);
    $esi->shouldReceive('getAffiliations')->andReturn(new EsiResult(
        data: [new CharacterAffiliation($characterId, $corporationId, $allianceId, null)],
    ));
    app()->instance(Esi::class, $esi);
}

it('redirects to the Alliance Auth authorization URL with valid state', function () {
    $response = $this->get(route('allianceauth.redirect'));

    $response->assertRedirect();
    $targetUrl = $response->headers->get('Location');

    expect($targetUrl)->toContain('https://auth.r3v-w.space/o/authorize/?')
        ->and($targetUrl)->toContain('client_id=test-client-id')
        ->and($targetUrl)->toContain('response_type=code')
        ->and(session('allianceauth_state'))->not()->toBeEmpty();
});

it('rejects callback with invalid or mismatched state', function () {
    session(['allianceauth_state' => 'correct-state']);

    $response = $this->get(route('allianceauth.callback', [
        'code' => 'valid-code',
        'state' => 'wrong-state',
    ]));

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('auth');
    $this->assertGuest();
});

it('logs in user via Alliance Auth callback when authorized', function () {
    session(['allianceauth_state' => 'test-state']);

    Http::fake([
        'https://auth.r3v-w.space/o/token/' => Http::response([
            'access_token' => 'mocked-aa-access-token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]),
        'https://auth.r3v-w.space/o/userinfo/' => Http::response([
            'sub' => '1001',
            'character_id' => 8888,
            'name' => 'Alliance Pilot',
            'email' => 'pilot@example.com',
            'groups' => ['Member', 'Wormhole Ops'],
        ]),
    ]);

    fakeAllianceAuthEsi(characterId: 8888, corporationId: 2000, allianceId: 3000);

    $response = $this->get(route('allianceauth.callback', [
        'code' => 'test-code',
        'state' => 'test-state',
    ]));

    $response->assertRedirect(route('home'));
    $this->assertAuthenticated();

    $user = auth()->user();
    expect($user)->toBeInstanceOf(User::class)
        ->and($user->name)->toBe('Alliance Pilot')
        ->and(Character::query()->find(8888))->not()->toBeNull();
});

it('blocks login if user lacks the required Alliance Auth group', function () {
    config()->set('services.allianceauth.required_groups', 'Wormhole Ops, Vanguard');
    session(['allianceauth_state' => 'test-state']);

    Http::fake([
        'https://auth.r3v-w.space/o/token/' => Http::response([
            'access_token' => 'mocked-aa-access-token',
            'token_type' => 'Bearer',
        ]),
        'https://auth.r3v-w.space/o/userinfo/' => Http::response([
            'sub' => '1001',
            'character_id' => 8888,
            'name' => 'Unauthorized Pilot',
            'groups' => ['Member', 'Miner'], // Missing Wormhole Ops or Vanguard
        ]),
    ]);

    fakeAllianceAuthEsi(characterId: 8888);

    $response = $this->get(route('allianceauth.callback', [
        'code' => 'test-code',
        'state' => 'test-state',
    ]));

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('auth');
    $this->assertGuest();
});

it('never removes a previously-recorded esi scope when a later login reports fewer', function () {
    session(['allianceauth_state' => 'test-state']);

    EsiScope::query()->create(['name' => 'publicData', 'is_default' => true]);
    EsiScope::query()->create(['name' => 'esi-location.read_location.v1', 'is_default' => false]);

    $character = Character::factory()->create(['id' => 8888]);
    $existingToken = $character->esiTokens()->create([
        'access_token' => 'stale-access-token',
        'refresh_token' => 'stale-refresh-token',
        'token_type' => 'character',
        'character_owner_hash' => 'owner-hash',
        'expires_at' => now()->addHour(),
    ]);
    $existingToken->esiScopes()->sync(EsiScope::query()->pluck('id'));

    Http::fake([
        'https://auth.r3v-w.space/o/token/' => Http::response([
            'access_token' => 'mocked-aa-access-token',
            'token_type' => 'Bearer',
        ]),
        'https://auth.r3v-w.space/o/userinfo/' => Http::response([
            'sub' => '1001',
            'character_id' => 8888,
            'name' => 'Alliance Pilot',
            'groups' => ['Member'],
            'characters' => [[
                'character_id' => 8888,
                'character_name' => 'Alliance Pilot',
                'access_token' => 'fresh-access-token',
                'refresh_token' => 'fresh-refresh-token',
                'expires_in' => 1200,
                // This login only reports publicData - a stale AA-side token
                // pick, not a real revocation of the location scope.
                'scopes' => ['publicData'],
            ]],
        ]),
    ]);

    fakeAllianceAuthEsi(characterId: 8888, corporationId: 2000, allianceId: 3000);

    $this->get(route('allianceauth.callback', [
        'code' => 'test-code',
        'state' => 'test-state',
    ]))->assertRedirect(route('home'));

    expect($existingToken->fresh()->esiScopes()->pluck('name')->all())
        ->toContain('publicData', 'esi-location.read_location.v1');
});

it('permits login if user has one of the required Alliance Auth groups', function () {
    config()->set('services.allianceauth.required_groups', 'Wormhole Ops, Vanguard');
    session(['allianceauth_state' => 'test-state']);

    Http::fake([
        'https://auth.r3v-w.space/o/token/' => Http::response([
            'access_token' => 'mocked-aa-access-token',
            'token_type' => 'Bearer',
        ]),
        'https://auth.r3v-w.space/o/userinfo/' => Http::response([
            'sub' => '1001',
            'character_id' => 8888,
            'name' => 'Authorized Pilot',
            'groups' => ['Member', 'Vanguard'],
        ]),
    ]);

    fakeAllianceAuthEsi(characterId: 8888);

    $response = $this->get(route('allianceauth.callback', [
        'code' => 'test-code',
        'state' => 'test-state',
    ]));

    $response->assertRedirect(route('home'));
    $this->assertAuthenticated();
});
