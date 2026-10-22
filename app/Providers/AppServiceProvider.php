<?php

namespace App\Providers;

use App\Models\Discussion;
use App\Models\Review;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        RateLimiter::for('verification', fn (Request $request): array => [
            Limit::perMinute(1)->by('verification-user:'.$request->user()->id)->response(
                fn (Request $request, array $headers) => back()->withErrors(['verification' => 'Please wait before requesting another verification email.'])->withHeaders($headers),
            ),
            Limit::perHour(6)->by('verification-hour:'.$request->user()->id)->response(
                fn (Request $request, array $headers) => back()->withErrors(['verification' => 'Too many verification requests. Please try again in an hour.'])->withHeaders($headers),
            ),
        ]);

        Relation::enforceMorphMap([
            'discussion' => Discussion::class,
            'review' => Review::class,
        ]);
    }
}
