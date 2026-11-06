<?php

namespace App\Providers;

use App\Models\Beer;
use App\Models\BeerPrice;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        // Stable names stored in points_events.reference_type.
        Relation::enforceMorphMap([
            'user' => User::class,
            'venue' => Venue::class,
            'beer' => Beer::class,
            'beer_price' => BeerPrice::class,
        ]);

        Gate::define('admin', fn (User $user) => $user->isAdmin());

        // cPanel / LiteSpeed often terminates TLS upstream.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        RateLimiter::for('contributions', fn (Request $request) => [
            Limit::perHour(30)->by('contrib:'.$request->user()?->id),
            Limit::perDay(100)->by('contrib-day:'.$request->user()?->id),
        ]);
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('geo', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
    }
}
