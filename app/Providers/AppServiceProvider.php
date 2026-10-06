<?php

namespace App\Providers;

use App\Http\Requests\Products\IndexProductRequest;
use App\Http\Requests\Users\IndexUserRequest;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! app()->isProduction());

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(
            Str::lower($request->string('email')->toString()).'|'.$request->ip()
        ));

        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(6)->by($request->ip()));

        // Filtered product lists skip the catalog cache, and every search runs a full LIKE scan,
        // so searches are limited while plain list pages stay free to browse. Products and
        // users have separate budgets, so one never blocks the other.
        RateLimiter::for('product-search', fn (Request $request): Limit => IndexProductRequest::hasFilterInput($request)
            ? $this->searchLimit($request)
            : Limit::none());

        RateLimiter::for('user-search', fn (Request $request): Limit => IndexUserRequest::hasFilterInput($request)
            ? $this->searchLimit($request)
            : Limit::none());
    }

    private function searchLimit(Request $request): Limit
    {
        // Search routes require a token; the IP is only a fallback, so a route without auth
        // could never end up sharing one global counter.
        return Limit::perMinute(60)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()));
    }
}
