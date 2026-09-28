<?php

use App\Models\Plan;
use App\Models\Shift;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', fn () => $this->toBe(1));

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function createOwnedPlan(User $owner): Plan
{
    return Plan::create([
        'user_id' => $owner->id,
        'title' => 'Test plan',
        'description' => 'Some description',
        'owner_email' => 'owner@example.com',
        'contact_email' => 'contact@example.com',
        'contact_phone' => '0123 456789',
    ]);
}

function createShiftForPlan(Plan $plan): Shift
{
    return $plan->shifts()->create([
        'title' => 'Test shift',
        'description' => 'Shift description',
        'group' => 1,
        'start' => now(),
        'end' => now()->addHours(2),
        'team_size' => 2,
    ]);
}

/**
 * Feeds the given Guzzle responses to the OIDC Socialite driver, in order -
 * the OIDC provider package talks to the IdP over a raw Guzzle client, not
 * Laravel's Http facade, so Http::fake() can't intercept it.
 *
 * @param  Response[]  $responses
 */
function fakeOidcHttp(array $responses): void
{
    $handlerStack = HandlerStack::create(new MockHandler($responses));

    Socialite::driver('openidconnect')->setHttpClient(new Client(['handler' => $handlerStack]));
}

function createPlanWithShift(): Shift
{
    $plan = Plan::create([
        'title' => 'Test plan',
        'description' => 'Some description',
        'owner_email' => 'owner@example.com',
        'contact_email' => 'contact@example.com',
        'published' => true,
        'active' => true,
    ]);

    return createShiftForPlan($plan);
}
