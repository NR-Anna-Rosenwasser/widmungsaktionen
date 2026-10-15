<?php

namespace App\Filament\Resources\Aktions\Pages;

use App\Filament\Pages\Moderation;
use App\Filament\Pages\Widmen;
use App\Filament\Resources\Aktions\AktionResource;
use App\Models\Aktion;
use App\Services\SigningService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
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
            DeleteAction::make(),
        ];
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
