<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Tenancy\EditTenantSettings;
use App\Filament\Pages\Tenancy\RegisterTenant;
use App\Models\Tenant;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->domain(config('widmungen.admin_domain'))
            ->path('')
            ->login()
            ->passwordReset()
            ->brandName('Widmungen')
            ->colors([
                // Hand-made scale around the brand navy (#2B3C69 = 600, used for buttons);
                // Color::hex() would normalise it to a much lighter blue.
                'primary' => [
                    50 => '#f3f5fa',
                    100 => '#e6eaf3',
                    200 => '#c9d1e5',
                    300 => '#a3b0d1',
                    400 => '#6f80ad',
                    500 => '#4a5a85',
                    600 => '#2b3c69',
                    700 => '#24335a',
                    800 => '#1e2a4a',
                    900 => '#18223b',
                    950 => '#10172a',
                ],
                'gray' => Color::Slate,
            ])
            ->tenant(Tenant::class, slugAttribute: 'slug')
            ->tenantRegistration(RegisterTenant::class)
            ->tenantProfile(EditTenantSettings::class)
            ->searchableTenantMenu()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
