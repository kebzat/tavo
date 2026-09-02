<?php

namespace App\Filament\Tools\Resources\Companies\RelationManagers;

use App\Enums\Crm\ActivityOutcome;
use App\Enums\Crm\ActivityType;
use App\Filament\Tools\Actions\LogActivityAction;
use App\Filament\Tools\Actions\UseTemplateAction;
use App\Models\Crm\Activity;
use App\Models\Crm\Company;
use App\Models\Crm\Contact;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Časová osa firmy. Nejnovější nahoře — při otevření karty nás zajímá,
 * co bylo naposledy, ne co bylo loni.
 */
class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $title = 'Časová osa';

    /**
     * Editační formulář je proti rychlému zápisu bohatší o navázání na obchod
     * a kontakt. Do rychlé akce ta pole nepatří — zdržovala by u devíti
     * záznamů z deseti.
     */
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')
                ->label('Typ')
                ->options(ActivityType::class)
                ->native(false)
                ->required(),

            DateTimePicker::make('happened_at')->label('Kdy')->seconds(false)->required(),

            TextInput::make('subject')->label('Předmět')->required()->maxLength(255)->columnSpanFull(),

            Textarea::make('body')->label('Text')->rows(5)->columnSpanFull(),

            Select::make('deal_id')
                ->label('Obchod')
                ->options(fn (): array => $this->companyRecord()->deals()->pluck('title', 'crm_deals.id')->all())
                ->native(false)
                ->placeholder('Bez vazby'),

            Select::make('contact_id')
                ->label('Kontakt')
                ->options(fn (): array => $this->companyRecord()->contacts
                    ->mapWithKeys(fn (Contact $c): array => [$c->getKey() => $c->label()])
                    ->all())
                ->native(false)
                ->placeholder('Bez vazby'),

            Select::make('outcome')
                ->label('Výsledek')
                ->options(ActivityOutcome::class)
                ->native(false)
                ->placeholder('Zatím nevíme'),

            DateTimePicker::make('follow_up_at')
                ->label('Ozvat se znovu')
                ->seconds(false)
                ->helperText('Nejbližší termín se propíše na kartu firmy.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('subject')
            ->defaultSort('happened_at', 'desc')
            ->columns([
                TextColumn::make('happened_at')
                    ->label('Kdy')
                    ->dateTime('j. n. Y H:i')
                    ->description(fn (Activity $record): ?string => $record->user?->name)
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Typ')
                    ->badge()
                    ->icon(fn (ActivityType $state) => $state->getIcon()),

                TextColumn::make('subject')
                    ->label('Co se dělo')
                    ->weight('bold')
                    ->wrap()
                    ->description(fn (Activity $record): ?string => $record->excerpt() ?: null)
                    ->searchable(),

                TextColumn::make('outcome')->label('Výsledek')->badge()->placeholder('—'),

                TextColumn::make('follow_up_at')
                    ->label('Follow-up')
                    ->dateTime('j. n. Y')
                    ->placeholder('—')
                    ->color(fn (Activity $record): ?string => $record->follow_up_at?->isPast() ? 'danger' : null),

                TextColumn::make('deal.title')->label('Obchod')->toggleable()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('type')->label('Typ')->options(ActivityType::class)->multiple(),
                SelectFilter::make('outcome')->label('Výsledek')->options(ActivityOutcome::class)->multiple(),
            ])
            ->headerActions([
                LogActivityAction::make()->arguments(['company' => $this->companyRecord()->getKey()]),
                UseTemplateAction::make()->arguments(['company' => $this->companyRecord()->getKey()]),
            ])
            ->recordActions([EditAction::make()->label('')->iconButton(), DeleteAction::make()->label('')->iconButton()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->emptyStateHeading('Zatím jsme nic neudělali')
            ->emptyStateDescription('Zaloguj první oslovení — z aktivit se počítají follow-upy i týdenní čísla.');
    }

    /**
     * Obchody a kontakty se v roletkách nabízejí jen z téhle firmy.
     * Vazba na obchod cizí firmy by rozbila i statistiky.
     */
    private function companyRecord(): Company
    {
        /** @var Company $company */
        $company = $this->getOwnerRecord();

        return $company;
    }
}
