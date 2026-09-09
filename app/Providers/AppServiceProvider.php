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

        // tc-lib-pdf ships no fonts at all - it only reads the definition
        // files its util/convert.php produces, and finds them either next to
        // its own source or through this constant. The Liberation Sans faces
        // in resources/fonts were converted from the upstream TTFs and cover
        // the Latin ranges the de/en/es locales need.
        if (! defined('K_PATH_FONTS')) {
            define('K_PATH_FONTS', resource_path('fonts'));
        }
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
