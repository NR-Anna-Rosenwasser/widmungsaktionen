<?php

namespace App\Filament\Pages\Tenancy;

use App\Filament\Schemas\TenantSettingsForm;
use App\Models\Tenant;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class EditTenantSettings extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Einstellungen';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Einstellungen';
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return TenantSettingsForm::fillDefaults($data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()
                ->persistTabInQueryString()
                ->tabs([
                    Tab::make('Shop')->schema([
                        ...TenantSettingsForm::general(),
                        Section::make('Eigene Domains')
                            ->description('Domains, die direkt auf diesen Shop zeigen (DNS auf den Server, Reverse Proxy konfigurieren).')
                            ->schema([
                                Repeater::make('domains')
                                    ->hiddenLabel()
                                    ->relationship()
                                    ->simple(
                                        TextInput::make('domain')
                                            ->required()
                                            ->regex('/^(?!-)[a-z0-9-]+(\.[a-z0-9-]+)+$/i')
                                            ->distinct()
                                            ->unique('tenant_domains', 'domain', ignoreRecord: true),
                                    )
                                    ->defaultItems(0)
                                    ->addActionLabel('Domain hinzufügen'),
                            ]),
                    ]),
                    Tab::make('Absender & Konto')->schema(TenantSettingsForm::seller()),
                    Tab::make('Bestellungen & Versand')->schema(TenantSettingsForm::ordering()),
                    Tab::make('Zahlung & E-Mail')->schema([
                        Section::make('Stripe')
                            ->description(fn (Tenant $record) => 'Webhook-URL für Stripe: '.route('webhooks.stripe', $record).' (Events: checkout.session.completed, checkout.session.async_payment_succeeded, checkout.session.async_payment_failed, checkout.session.expired, charge.refunded)')
                            ->schema(TenantSettingsForm::stripe()),
                        Section::make('E-Mail')->schema(TenantSettingsForm::mail()),
                    ]),
                    Tab::make('Newsletter & Rechtliches')->schema([
                        ...TenantSettingsForm::newsletter(),
                        ...TenantSettingsForm::legal(),
                    ]),
                ]),
        ]);
    }
}
