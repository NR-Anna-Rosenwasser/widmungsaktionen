<?php

namespace App\Filament\Pages\Tenancy;

use App\Enums\TenantRole;
use App\Filament\Schemas\TenantSettingsForm;
use App\Models\Tenant;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant as BaseRegisterTenant;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * "Neuer Shop" wizard. Only super admins can open it (TenantPolicy::create).
 */
class RegisterTenant extends BaseRegisterTenant
{
    public static function getLabel(): string
    {
        return 'Neuer Shop';
    }

    /**
     * Wider than Filament's default for simple pages, so address and
     * shipping fields fit side by side.
     */
    public function getMaxWidth(): Width|string|null
    {
        return Width::SixExtraLarge;
    }

    public function mount(): void
    {
        parent::mount();

        $this->form->fill(TenantSettingsForm::fillDefaults(['is_active' => true, 'creditor_country' => 'CH']));
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Wizard::make([
                Step::make('Shop')->schema([...TenantSettingsForm::general(), ...TenantSettingsForm::languages()]),
                Step::make('Absender & Konto')->schema(TenantSettingsForm::seller()),
                Step::make('Bestellungen & Versand')->schema(TenantSettingsForm::ordering()),
                Step::make('Stripe & E-Mail')->schema([
                    ...TenantSettingsForm::stripe(),
                    ...TenantSettingsForm::mail(),
                ]),
                Step::make('Erste*r Admin')->schema([
                    Select::make('admin_user_id')
                        ->label('Bestehende*r Benutzer*in')
                        ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->live()
                        ->helperText('Leer lassen, um eine neue Person anzulegen.'),
                    TextInput::make('admin_name')
                        ->label('Name')
                        ->required(fn (Get $get) => blank($get('admin_user_id')))
                        ->visible(fn (Get $get) => blank($get('admin_user_id'))),
                    TextInput::make('admin_email')
                        ->label('E-Mail')
                        ->email()
                        ->unique(User::class, 'email')
                        ->required(fn (Get $get) => blank($get('admin_user_id')))
                        ->visible(fn (Get $get) => blank($get('admin_user_id'))),
                    TextInput::make('admin_password')
                        ->label('Passwort')
                        ->password()
                        ->revealable()
                        ->minLength(12)
                        ->required(fn (Get $get) => blank($get('admin_user_id')))
                        ->visible(fn (Get $get) => blank($get('admin_user_id'))),
                ]),
            ])->submitAction($this->getRegisterFormAction()),
        ]);
    }

    protected function getFormActions(): array
    {
        // The wizard renders the submit button on its last step.
        return [];
    }

    protected function handleRegistration(array $data): Model
    {
        $admin = Arr::pull($data, 'admin_user_id');
        $adminData = [
            'name' => Arr::pull($data, 'admin_name'),
            'email' => Arr::pull($data, 'admin_email'),
            'password' => Arr::pull($data, 'admin_password'),
        ];

        $tenant = Tenant::create($data);

        $user = $admin ? User::findOrFail($admin) : User::create($adminData);
        $tenant->users()->attach($user, ['role' => TenantRole::Admin->value]);

        // The super admin creating the shop sees it via is_super_admin; no membership needed.
        return $tenant;
    }
}
