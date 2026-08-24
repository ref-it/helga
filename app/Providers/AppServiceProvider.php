<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\DescriptionSanitizer;
use App\Support\OidcProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        config(['purifier.settings.description' => [
            'HTML.Allowed' => DescriptionSanitizer::ALLOWED_HTML,
        ]]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('oidc', OidcProvider::class);
        });
    }
}
