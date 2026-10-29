<?php

namespace App\Filament\Pages;

use App\Enums\Locale;
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
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * All customer-facing texts of the shop (TextCatalog), one area at a time,
 * one tab per language of the shop. Empty fields use the default, shown in
 * grey; the other languages show the default language's text for reference.
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

        foreach ($this->locales() as $locale) {
            foreach ($this->keys() as $key) {
                data_set($data, "$locale.$key", data_get($settings, "texts.$locale.$key"));
            }
        }

        $this->form->fill($data);
    }

    /**
     * The shop's languages, default first.
     *
     * @return array<int, string>
     */
    protected function locales(): array
    {
        $tenant = filament()->getTenant();

        return array_values(array_unique([$tenant->defaultLocale(), ...$tenant->locales()]));
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
        $extra = $group['extra'] ?? null;
        $locales = $this->locales();
        $sections = fn (string $locale) => collect($group['texts'])->keys()->map(fn (string $key) => $this->section($key, $locale))->all();

        return $schema
            ->statePath('data')
            ->components([
                count($locales) === 1
                    ? Grid::make(1)->schema($sections($locales[0]))
                    : Tabs::make('Sprache')->persistTabInQueryString('sprache')->tabs(array_map(
                        fn (string $locale) => Tab::make(Locale::from($locale)->getLabel())->schema($sections($locale)),
                        $locales,
                    )),
                ...($extra ? [Section::make('Zusatztext der Aktion')
                    ->description("Pro Aktion kannst du hier einen zusätzlichen Text ergänzen: «{$extra[1]}». Du findest ihn beim Bearbeiten der Aktion unter «Zusatztexte».")
                    ->compact()] : []),
            ]);
    }

    /**
     * One text in one language. Outside the default language, the current
     * text of the default language is shown as a reference for translating.
     */
    protected function section(string $key, string $locale): Section
    {
        $text = TextCatalog::texts()[$key];
        $tenant = filament()->getTenant();
        $default = $tenant->defaultLocale();
        $field = self::field($key, $locale, $text['kind'], $text['placeholders'])->hiddenLabel();

        if ($locale !== $default) {
            $field->helperText(Locale::from($default)->getLabel().': '.Str::limit(app(Texts::class)->raw($tenant, $key, $default), 400));
        }

        return Section::make($text['label'])
            ->description('Platzhalter: '.collect($text['placeholders'])->map(fn ($p) => '{'.$p.'}')->join(', ')
                .(TextCatalog::isMarkdown($text['kind']) ? ' · Formatierung möglich' : ''))
            ->compact()
            ->schema([$field]);
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
            ->label(Locale::from($locale)->getLabel())
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

        // Languages the shop doesn't offer (any more) keep their texts.
        foreach ($this->locales() as $locale) {
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
