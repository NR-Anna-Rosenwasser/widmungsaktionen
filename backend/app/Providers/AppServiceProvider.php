<?php

namespace App\Providers;

use App\Listeners\SendOrderMails;
use App\Models\Tenant;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\StripeGateway;
use Filament\Events\TenantSet;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Sentry\State\Scope;

use function Sentry\configureScope;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, StripeGateway::class);
    }

    public function boot(): void
    {
        JsonResource::withoutWrapping();

        Event::subscribe(SendOrderMails::class);

        RateLimiter::for('storefront', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('order-lookup', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('address-search', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        // Tag error reports with the shop (storefront API and admin panel).
        // RouteMatched runs before model binding, so the parameter is still the slug.
        $tagTenant = fn (mixed $tenant) => configureScope(fn (Scope $scope) => $scope->setTag('tenant', $tenant instanceof Tenant ? $tenant->slug : (string) $tenant));
        Event::listen(RouteMatched::class, function (RouteMatched $event) use ($tagTenant) {
            if ($event->route->hasParameter('tenant')) {
                $tagTenant($event->route->parameter('tenant'));
            }
        });
        Event::listen(TenantSet::class, fn (TenantSet $event) => $tagTenant($event->getTenant()));
    }
}
