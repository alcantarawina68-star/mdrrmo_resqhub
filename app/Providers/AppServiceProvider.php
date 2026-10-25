<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Semaphore\SemaphoreClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SemaphoreClient::class, function () {
            $config = config('services.sms.semaphore', []);

            $options = [
                'apiBase' => rtrim((string) ($config['url'] ?? ''), '/').'/',
                'timeout' => (int) ($config['timeout'] ?? 15),
            ];

            if (! blank($config['sender'] ?? null)) {
                $options['sendername'] = $config['sender'];
            }

            return new SemaphoreClient((string) ($config['key'] ?? ''), $options);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(100)->by($request->user()->id)
                : Limit::perMinute(30)->by($request->ip());
        });

        // Guest reports need no account, so the form is a public write endpoint.
        // Key on the IP because there is no user to key on.
        RateLimiter::for('public-reports', function (Request $request) {
            return [
                Limit::perMinute(10)->by($request->ip()),
                Limit::perHour(30)->by($request->ip()),
            ];
        });
    }
}
