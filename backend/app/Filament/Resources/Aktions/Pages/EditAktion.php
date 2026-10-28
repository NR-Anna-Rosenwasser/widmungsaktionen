<?php

namespace App\Filament\Resources\Aktions\Pages;

use App\Filament\Pages\Moderation;
use App\Filament\Pages\Widmen;
use App\Filament\Resources\Aktions\AktionResource;
use App\Models\Aktion;
use App\Services\SigningService;
use App\Services\TestMode;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
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
            self::prepareAction(),
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
                ->modalDescription('Sendet sofort, was sonst warten würde: Erinnerungen an nicht abgeschlossene Bestellungen (sonst nach 24 Stunden), Zahlungserinnerungen (sonst kurz vor Bestellschluss) und «Dein Buch ist gewidmet» (sonst 30 Minuten nach dem Widmen).')
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
     * Assign post-it numbers. Shared with the Aktionen table.
     */
    public static function prepareAction(): Action
    {
        return Action::make('prepare')
            ->label('Widmen vorbereiten')
            ->icon(Heroicon::OutlinedQueueList)
            ->color('primary')
            ->modalHeading('Stapel für den Widmungstag vorbereiten')
            ->modalDescription(fn (Aktion $record) => self::readinessText($record))
            ->modalSubmitActionLabel('Post-it-Nummern vergeben')
            ->modalSubmitAction(fn (Action $action, Aktion $record) => $action->disabled(
                app(SigningService::class)->readiness($record)['pending_moderation'] > 0
                || app(SigningService::class)->readiness($record)['ready'] === 0,
            ))
            ->action(function (Aktion $record) {
                try {
                    $count = app(SigningService::class)->prepare($record, auth()->user());
                    Notification::make()
                        ->title("$count Bestellungen haben eine Post-it-Nummer")
                        ->body('Die Liste zum Beschriften der Post-its findest du unter «Drucken».')
                        ->success()
                        ->send();
                } catch (RuntimeException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    protected static function readinessText(Aktion $record): HtmlString
    {
        $r = app(SigningService::class)->readiness($record);
        $lines = [];

        $lines[] = $r['ready'] > 0
            ? "<strong>{$r['ready']}</strong> bestätigte Bestellungen bekommen eine Post-it-Nummer"
                .($r['stacked'] ? " (fortlaufend nach den {$r['stacked']} bereits nummerierten)." : '.')
            : 'Keine neuen Bestellungen zum Nummerieren.';

        if ($r['pending_moderation'] > 0) {
            $lines[] = "<strong>{$r['pending_moderation']}</strong> Widmungswünsche sind noch nicht freigegeben. "
                .'<a href="'.e(Moderation::getUrl()).'" style="text-decoration: underline">Zuerst prüfen</a>.';
        }
        if ($r['flagged'] > 0) {
            $lines[] = "<strong>{$r['flagged']}</strong> Wünsche haben eine Rückfrage; diese Bestellungen kommen erst später auf den Stapel.";
        }
        if ($r['unpaid'] > 0) {
            $lines[] = "<strong>{$r['unpaid']}</strong> Bestellungen sind noch unbezahlt und werden nicht gewidmet.";
        }

        return new HtmlString(implode('<br>', $lines));
    }
}
