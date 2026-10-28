<?php

namespace App\Filament\Resources\Aktions\Pages;

use App\Enums\AktionStatus;
use App\Filament\Pages\Versand;
use App\Filament\Pages\Widmen;
use App\Filament\Resources\Aktions\AktionResource;
use App\Models\Aktion;
use App\Services\TestMode;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use RuntimeException;

/**
 * @property Aktion $record
 */
class EditAktion extends EditRecord
{
    protected static string $resource = AktionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            self::versandAction(),
            Action::make('widmen')
                ->label('Zum Widmen')
                ->icon(Heroicon::OutlinedPencil)
                ->color('gray')
                ->visible(fn () => $this->record->orders()->whereNotNull('stack_number')->exists())
                ->url(fn () => Widmen::getUrl(['aktion' => $this->record->id])),
            $this->testModeActions(),
            // A test Aktion takes its test orders with it.
            DeleteAction::make()->before(fn (TestMode $testMode) => $this->record->is_test && $testMode->deleteOrders($this->record)),
        ];
    }

    /**
     * Only while the Aktion is in testing mode.
     */
    protected function testModeActions(): ActionGroup
    {
        $done = function (string $title) {
            Notification::make()->title($title)->success()->send();
            $this->refreshFormData(['is_test', 'preview_link']);
        };
        $failed = fn (RuntimeException $e) => Notification::make()->title($e->getMessage())->danger()->send();

        return ActionGroup::make([
            Action::make('sendPendingMails')
                ->label('Wartende E-Mails jetzt senden')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->requiresConfirmation()
                ->modalDescription('Sendet sofort, was sonst warten würde: Erinnerungen an nicht abgeschlossene Bestellungen (sonst nach 24 Stunden), Zahlungserinnerungen (sonst kurz vor Bestellschluss) und «Dein Buch ist gewidmet» (sonst 30 Minuten nach dem Widmen, nur wenn in den Einstellungen aktiviert).')
                ->action(function (TestMode $testMode) use ($done) {
                    $sent = $testMode->sendPendingMails($this->record);
                    $done("{$sent['abandoned']} Erinnerungen, {$sent['reminders']} Zahlungserinnerungen, {$sent['signed']} «Gewidmet»-E-Mails gesendet");
                }),
            Action::make('regenerateLink')
                ->label('Neuen Link erzeugen')
                ->icon(Heroicon::OutlinedArrowPath)
                ->requiresConfirmation()
                ->modalDescription('Der bisherige Link funktioniert danach nicht mehr.')
                ->action(function (TestMode $testMode) use ($done) {
                    $testMode->regenerateLink($this->record);
                    $done('Neuer Link erzeugt');
                }),
            Action::make('deleteTestOrders')
                ->label('Alle Testbestellungen löschen')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription(fn () => $this->record->orders()->count().' Bestellungen (inkl. Entwürfe) werden endgültig gelöscht.')
                ->action(function (TestMode $testMode) use ($done) {
                    $done($testMode->deleteOrders($this->record).' Testbestellungen gelöscht');
                }),
            Action::make('endTestMode')
                ->label('Testmodus beenden')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('Danach ist die Aktion echt: Sie erscheint im Shop, sobald ihr Status es erlaubt, und der geheime Link funktioniert nicht mehr.')
                ->schema(fn () => $this->record->orders()->exists() ? [
                    Toggle::make('delete_orders')
                        ->label($this->record->orders()->count().' Testbestellungen löschen')
                        ->helperText('Nötig: Testbestellungen dürfen nicht in einer echten Aktion bleiben.')
                        ->accepted()
                        ->default(false),
                ] : [])
                ->action(function (array $data, TestMode $testMode) use ($done, $failed) {
                    try {
                        $testMode->end($this->record, (bool) ($data['delete_orders'] ?? false));
                        $done('Testmodus beendet');
                    } catch (RuntimeException $e) {
                        $failed($e);
                    }
                }),
        ])
            ->label('Testmodus')
            ->icon(Heroicon::OutlinedBeaker)
            ->button()
            ->color('warning')
            ->visible(fn () => $this->record->is_test);
    }

    /**
     * To «Vorbereiten & Versand» for this Aktion. Shared with the Aktionen table.
     */
    public static function versandAction(): Action
    {
        return Action::make('versand')
            ->label('Vorbereiten & Versand')
            ->icon(Heroicon::OutlinedQueueList)
            ->color('primary')
            ->visible(fn (Aktion $record) => $record->status !== AktionStatus::Draft && Versand::canAccess())
            ->url(fn (Aktion $record) => Versand::getUrl(['aktion' => $record->id]));
    }
}
