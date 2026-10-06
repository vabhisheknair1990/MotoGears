<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        JsonResource::withoutWrapping();

        // Surface accidental N+1 queries in development logs without breaking requests.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation) {
            logger()->debug('Lazy loaded '.$relation.' on '.$model::class);
        });

        // Super admins pass every policy / gate check.
        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);

        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(180)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('auth', fn (Request $r) => [
            Limit::perMinute(10)->by('auth-ip:'.$r->ip()),
            Limit::perMinute(5)->by('auth-email:'.strtolower((string) $r->input('email')).$r->ip()),
        ]);
        RateLimiter::for('forms', fn (Request $r) => Limit::perMinute(5)->by($r->ip()));
        RateLimiter::for('search', fn (Request $r) => Limit::perMinute(90)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('webhooks', fn (Request $r) => Limit::perMinute(300)->by('webhook:'.$r->ip()));
        RateLimiter::for('uploads', fn (Request $r) => Limit::perMinute(20)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('checkout', fn (Request $r) => Limit::perMinute(10)->by($r->user()?->id ?: $r->ip()));
    }
}
