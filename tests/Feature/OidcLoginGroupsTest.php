<?php

use App\Models\Group;
use App\Models\User;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

function fakeOidcLogin(array $groups, string $sub = 'sub-123', ?string $phoneNumber = null): void
{
    $socialiteUser = (new SocialiteUser)
        ->setRaw(['groups' => $groups, 'sid' => 'sid-'.$sub, 'phone_number' => $phoneNumber])
        ->map([
            'id' => $sub,
            'name' => 'Test User',
            'email' => 'test-'.$sub.'@example.com',
            'given_name' => 'Test',
            'family_name' => 'User',
        ]);

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);
    $provider->shouldReceive('getIdToken')->andReturn('fake-id-token');
    Socialite::shouldReceive('driver')->with('oidc')->andReturn($provider);
}

test('logging in stores every group from the ID token in the central registry', function (): void {
    fakeOidcLogin(['helpers', 'admins']);

    $this->get(route('oidc.callback'))->assertRedirect(route('plan.mine'));

    expect(Group::pluck('name')->sort()->values()->all())->toBe(['admins', 'helpers']);
    expect(User::where('sub', 'sub-123')->firstOrFail()->groups)->toBe(['helpers', 'admins']);
});

test('groups already known are not duplicated across logins', function (): void {
    Group::create(['name' => 'helpers']);

    fakeOidcLogin(['helpers', 'admins']);

    $this->get(route('oidc.callback'));

    expect(Group::where('name', 'helpers')->count())->toBe(1);
    expect(Group::pluck('name')->sort()->values()->all())->toBe(['admins', 'helpers']);
});

test('a user with no groups claim logs in without error and adds nothing new', function (): void {
    fakeOidcLogin([]);

    $this->get(route('oidc.callback'))->assertRedirect(route('plan.mine'));

    expect(Group::count())->toBe(0);
});

test('logging in stores the phone_number claim from the ID token, if present', function (): void {
    fakeOidcLogin([], phoneNumber: '+49 1520 1234567');

    $this->get(route('oidc.callback'))->assertRedirect(route('plan.mine'));

    expect(User::where('sub', 'sub-123')->firstOrFail()->phone)->toBe('+49 1520 1234567');
});

test('a user with no phone_number claim logs in without error and keeps no phone on file', function (): void {
    fakeOidcLogin([]);

    $this->get(route('oidc.callback'))->assertRedirect(route('plan.mine'));

    expect(User::where('sub', 'sub-123')->firstOrFail()->phone)->toBeNull();
});
