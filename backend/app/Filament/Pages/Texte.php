<?php

namespace App\Filament\Pages;

use App\Enums\TenantRole;
use App\Support\Texts\TextCatalog;
use App\Support\Texts\Texts;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * All customer-facing texts of the shop (TextCatalog), one area at a time.
 * Empty fields use the default, shown in grey.
 */
class Texte extends Page
{
    protected static ?string $slug = 'texte';

    protected static ?string $title = 'Texte';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static string|UnitEnum|null $navigationGroup = 'Einstellungen';

    #[Url]
    public string $bereich = 'home';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $tenant = filament()->getTenant();

        return $tenant && auth()->user()?->hasRoleIn($tenant, TenantRole::Admin);
    }

    public function mount(): void
    {
        if (! array_key_exists($this->bereich, TextCatalog::groups())) {
            $this->bereich = 'home';
        }

        $this->fillForm();
    }

    public function fillForm(): void
    {
        $settings = filament()->getTenant()->settings ?? [];
        $data = [];

        foreach (TextCatalog::LOCALES as $locale) {
            foreach ($this->keys() as $key) {
                data_set($data, "$locale.$key", data_get($settings, "texts.$locale.$key"));
            }
        }

        $this->form->fill($data);
    }

    /**
     * @return array<int, string>
     */
    protected function keys(): array
    {
        return array_keys(TextCatalog::groups()[$this->bereich]['texts']);
    }

    public function chooser(Schema $schema): Schema
    {
        $options = collect(TextCatalog::groups())
            ->groupBy('area', preserveKeys: true)
            ->map(fn ($groups) => $groups->map(fn (array $group) => $group['label'])->all())
            ->all();

        return $schema->components([
            Select::make('bereich')
                ->label('Bereich')
                ->options($options)
                ->selectablePlaceholder(false)
                ->native(false)
                ->live()
                ->afterStateUpdated(fn () => $this->fillForm()),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $group = TextCatalog::groups()[$this->bereich];
        $catalog = TextCatalog::texts();
        $extra = $group['extra'] ?? null;

        return $schema
            ->statePath('data')
            ->components([
                ...collect($group['texts'])->keys()->map(fn (string $key) => Section::make($catalog[$key]['label'])
                    ->description('Platzhalter: '.collect($catalog[$key]['placeholders'])->map(fn ($p) => '{'.$p.'}')->join(', ')
                        .(TextCatalog::isMarkdown($catalog[$key]['kind']) ? ' · Formatierung möglich' : ''))
                    ->compact()
                    ->schema([
                        Grid::make(2)->schema(array_map(
                            fn (string $locale) => self::field($key, $locale, $catalog[$key]['kind'], $catalog[$key]['placeholders']),
                            TextCatalog::LOCALES,
                        )),
                    ]))->all(),
                ...($extra ? [Section::make('Zusatztext der Aktion')
                    ->description("Pro Aktion kannst du hier einen zusätzlichen Text ergänzen: «{$extra[1]}». Du findest ihn beim Bearbeiten der Aktion unter «Zusatztexte».")
                    ->compact()] : []),
            ]);
    }

    /**
     * One text field for one locale, with the default as placeholder.
     *
     * @param  array<int, string>  $placeholders
     */
    public static function field(string $key, string $locale, string $kind, array $placeholders, ?string $default = null, string $statePrefix = ''): Field
    {
        $name = $statePrefix.$locale.'.'.$key;
        $default ??= app(Texts::class)->default($key, $locale);

        $field = match ($kind) {
            TextCatalog::MARKDOWN => MarkdownEditor::make($name)->toolbarButtons([['bold', 'italic', 'link'], ['bulletList'], ['undo', 'redo']])->maxLength(3000),
            TextCatalog::PRINT => MarkdownEditor::make($name)->toolbarButtons([['bold', 'italic'], ['undo', 'redo']])->maxLength(2000),
            TextCatalog::LONG => Textarea::make($name)->rows(2)->autosize()->maxLength(1000),
            default => TextInput::make($name)->maxLength(255),
        };

        $field
            ->label($locale === 'de' ? 'Deutsch' : 'English')
            ->placeholder($default)
            ->rule(fn () => function (string $attribute, mixed $value, Closure $fail) use ($placeholders) {
                $unknown = Texts::unknownPlaceholders((string) $value, $placeholders);
                if ($unknown) {
                    $fail('Unbekannter Platzhalter: {'.implode('}, {', $unknown).'}. Möglich: {'.implode('}, {', $placeholders).'}.');
                }
            });

        if ($default !== '') {
            $field->hintAction(
                Action::make('default_'.md5($name))
                    ->label('Standardtext übernehmen')
                    ->icon(Heroicon::OutlinedArrowDownOnSquare)
                    ->action(fn (Set $set) => $set($field, $default)),
            );
        }

        return $field;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedSchema::make('chooser'),
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([Action::make('save')->label('Speichern')->submit('save')->keyBindings(['mod+s'])])->sticky(),
                ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $tenant = filament()->getTenant();
        $settings = $tenant->settings ?? [];
        $texts = app(Texts::class);

        foreach (TextCatalog::LOCALES as $locale) {
            foreach ($this->keys() as $key) {
                $value = trim(str_replace("\r\n", "\n", (string) data_get($state, "$locale.$key")));

                // Unchanged defaults are not stored, so later improvements still arrive.
                if ($value === '' || $value === $texts->default($key, $locale)) {
                    Arr::forget($settings, "texts.$locale.$key");
                } else {
                    Arr::set($settings, "texts.$locale.$key", $value);
                }
            }
        }

        $tenant->update(['settings' => $settings]);
        $this->fillForm();

        Notification::make()->success()->title('Gespeichert')->send();
    }
}
