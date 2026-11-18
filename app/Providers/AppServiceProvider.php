<?php

namespace App\Providers;

use App\Mail\GraphTransport;
use App\Support\Settings;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\MicrosoftExtendSocialite;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);
    }

    public function boot(): void
    {
        Event::listen(SocialiteWasCalled::class, [MicrosoftExtendSocialite::class, 'handle']);

        Mail::extend('graph', fn () => new GraphTransport(
            (string) config('services.microsoft.tenant'),
            (string) config('services.microsoft.client_id'),
            (string) config('services.microsoft.client_secret'),
        ));
    }
}
