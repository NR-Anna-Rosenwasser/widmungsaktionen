<?php

namespace App\Filament\Resources\Team;

use App\Enums\TenantRole;
use App\Exceptions\TeamException;
use App\Filament\Resources\Team\Pages\ListTeam;
use App\Models\User;
use App\Services\TeamService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The people working in the current shop and their roles.
 */
class TeamResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'team';

    // Scoped manually through the tenant_user pivot below.
    protected static bool $isScopedToTenant = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Einstellungen';

    protected static ?string $modelLabel = 'Person';

    protected static ?string $pluralModelLabel = 'Team';

    public static function canAccess(): bool
    {
        $tenant = filament()->getTenant();

        return $tenant && auth()->user()?->hasRoleIn($tenant, TenantRole::Admin);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->join('tenant_user', 'tenant_user.user_id', '=', 'users.id')
            ->where('tenant_user.tenant_id', filament()->getTenant()->getKey())
            ->select('users.*', 'tenant_user.role as tenant_role');
    }

    public static function table(Table $table): Table
    {
        $team = app(TeamService::class);
        $roleOptions = TenantRole::class;

        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Name')->searchable(),
                TextColumn::make('email')->label('E-Mail')->searchable()->copyable(),
                TextColumn::make('tenant_role')
                    ->label('Rolle')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => TenantRole::from($state)->getLabel()),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Person hinzufügen')
                    ->modalHeading('Person hinzufügen')
                    ->modalDescription('Neue Personen bekommen eine E-Mail, um ihr Passwort zu setzen.')
                    ->schema([
                        TextInput::make('name')->label('Name')->required()->maxLength(255),
                        TextInput::make('email')->label('E-Mail')->email()->required(),
                        Select::make('role')->label('Rolle')->options($roleOptions)->default(TenantRole::Widmen)->required(),
                    ])
                    ->using(function (array $data, CreateAction $action) use ($team) {
                        try {
                            return $team->add(filament()->getTenant(), $data['name'], $data['email'], self::role($data['role']));
                        } catch (TeamException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                            $action->halt();
                        }
                    })
                    ->successNotificationTitle('Person hinzugefügt'),
            ])
            ->recordActions([
                Action::make('role')
                    ->label('Rolle ändern')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->fillForm(fn (User $record) => ['role' => $record->tenant_role])
                    ->schema([
                        Select::make('role')->label('Rolle')->options($roleOptions)->required(),
                    ])
                    ->action(fn (User $record, array $data) => self::attempt(
                        fn () => $team->changeRole(filament()->getTenant(), $record, self::role($data['role'])),
                        'Rolle geändert',
                    )),
                Action::make('resend')
                    ->label('Passwort-Link senden')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->requiresConfirmation()
                    ->action(function (User $record) use ($team) {
                        $team->sendPasswordSetupLink($record);
                        Notification::make()->title('Link gesendet')->success()->send();
                    }),
                Action::make('remove')
                    ->label('Entfernen')
                    ->icon(Heroicon::OutlinedUserMinus)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Die Person verliert den Zugriff auf diesen Shop. Ihr Konto bleibt bestehen.')
                    ->hidden(fn (User $record) => $record->is(auth()->user()))
                    ->action(fn (User $record) => self::attempt(
                        fn () => $team->remove(filament()->getTenant(), $record),
                        'Person entfernt',
                    )),
            ]);
    }

    protected static function role(TenantRole|string $role): TenantRole
    {
        return $role instanceof TenantRole ? $role : TenantRole::from($role);
    }

    protected static function attempt(callable $change, string $success): void
    {
        try {
            $change();
            Notification::make()->title($success)->success()->send();
        } catch (TeamException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTeam::route('/'),
        ];
    }
}
