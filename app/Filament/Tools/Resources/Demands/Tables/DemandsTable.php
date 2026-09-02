<?php

namespace App\Filament\Tools\Resources\Demands\Tables;

use App\Enums\Crm\ActivityType;
use App\Enums\Crm\CompanySegment;
use App\Enums\Crm\CompanyStatus;
use App\Enums\Crm\DemandSource;
use App\Enums\Crm\DemandStatus;
use App\Enums\Crm\Priority;
use App\Filament\Tools\Resources\Companies\CompanyResource;
use App\Models\Crm\Company;
use App\Models\Crm\Demand;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class DemandsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('company'))
            ->defaultSort(fn (Builder $query) => $query->orderBy('status')->orderBy('priority')->orderByDesc('posted_at'))
            ->persistFiltersInSession()
            ->columns([
                TextColumn::make('title')
                    ->label('Poptávka')
                    ->searchable()
                    ->weight('bold')
                    ->wrap()
                    // Tabulka má automatické rozvržení, takže sloupec s nejdelším
                    // textem ustoupí všem ostatním a název se láme po jednom
                    // slově. Procentní šířka je v auto-layoutu jen doporučení,
                    // minimální šířka v pixelech drží.
                    ->extraHeaderAttributes(['style' => 'min-width: 22rem'])
                    ->description(fn (Demand $record): ?string => $record->summary ? Str::limit($record->summary, 110) : null)
                    ->url(fn (Demand $record): string => $record->url, shouldOpenInNewTab: true),

                TextColumn::make('source')->label('Zdroj')->badge()->color('gray'),

                TextColumn::make('priority')
                    ->label('Priorita')
                    ->badge()
                    ->formatStateUsing(fn (Priority $state): string => $state->short())
                    ->sortable(),

                TextColumn::make('status')->label('Stav')->badge()->sortable(),

                TextColumn::make('budget_estimate')->label('Rozpočet')->placeholder('—')->toggleable(),

                TextColumn::make('posted_at')->label('Zveřejněno')->date('j. n. Y')->sortable()->placeholder('—'),

                TextColumn::make('company.name')
                    ->label('Firma')
                    ->placeholder('—')
                    ->url(fn (Demand $record): ?string => $record->company_id
                        ? CompanyResource::getUrl('edit', ['record' => $record->company_id])
                        : null)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('source')->label('Zdroj')->options(DemandSource::class)->multiple(),
                SelectFilter::make('status')->label('Stav')->options(DemandStatus::class)->multiple(),
                SelectFilter::make('priority')->label('Priorita')->options(Priority::class)->multiple(),
            ])
            ->recordActions([
                self::replied(),
                self::createCompany(),
                EditAction::make()->label('')->iconButton()->tooltip('Upravit'),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->emptyStateHeading('Žádné poptávky')
            ->emptyStateDescription('Ranní import je doplní sám. Ručně se přidávají přes „Přidat poptávku".')
            ->emptyStateIcon(Heroicon::OutlinedInboxArrowDown);
    }

    /**
     * Nejčastější úkon nad poptávkou: odepsali jsme. Kromě stavu se zapíše
     * i aktivita — když je poptávka navázaná na firmu, patří reakce do její
     * časové osy i do týdenních čísel.
     */
    private static function replied(): Action
    {
        return Action::make('markReplied')
            ->label('Reagováno')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('success')
            ->visible(fn (Demand $record): bool => $record->status === DemandStatus::New)
            ->modalHeading('Označit poptávku jako vyřízenou')
            ->modalSubmitActionLabel('Označit')
            ->schema([
                Textarea::make('body')
                    ->label('Co jsme napsali')
                    ->rows(4)
                    ->helperText('Nepovinné. Uloží se do časové osy firmy, pokud je navázaná.'),
            ])
            ->action(function (Demand $record, array $data): void {
                $record->update([
                    'status' => DemandStatus::Replied,
                    'replied_at' => now(),
                ]);

                $record->company?->activities()->create([
                    'user_id' => Auth::id(),
                    'type' => ActivityType::DemandReply,
                    'subject' => 'Reakce na poptávku: '.$record->title,
                    'body' => $data['body'] ?? null,
                    'happened_at' => now(),
                ]);

                Notification::make()->success()->title('Poptávka označena jako vyřízená')->send();
            });
    }

    /**
     * Z poptávky se stává firma ve chvíli, kdy se rozjede jednání. Předvyplní
     * se z toho, co o zadavateli víme, a poptávka se na novou kartu naváže.
     */
    private static function createCompany(): Action
    {
        return Action::make('createCompany')
            ->label('Založit firmu')
            ->icon(Heroicon::OutlinedBuildingStorefront)
            ->color('gray')
            ->visible(fn (Demand $record): bool => $record->company_id === null)
            ->modalHeading('Založit firmu z poptávky')
            ->modalSubmitActionLabel('Založit')
            ->fillForm(fn (Demand $record): array => [
                'name' => $record->title,
                'segment' => CompanySegment::Other->value,
                'priority' => $record->priority->value,
                'pain' => $record->summary,
            ])
            ->schema([
                TextInput::make('name')->label('Název firmy')->required()->maxLength(255),
                TextInput::make('website')->label('Web')->maxLength(255),
                Select::make('segment')->label('Segment')->options(CompanySegment::class)->native(false)->required(),
                Select::make('priority')->label('Priorita')->options(Priority::class)->native(false)->required(),
                Textarea::make('pain')->label('Co poptávají')->rows(3),
            ])
            ->action(function (Demand $record, array $data): void {
                $company = Company::create($data + [
                    // Zdroj firmy se odvodí z portálu, ať je v přehledu vidět,
                    // který portál doopravdy přináší zakázky.
                    'source' => $record->source->toCompanySource(),
                    'status' => CompanyStatus::Contacted,
                    'owner_id' => Auth::id(),
                    'notes' => 'Vzniklo z poptávky: '.$record->url,
                ]);

                $record->update(['company_id' => $company->getKey()]);

                Notification::make()
                    ->success()
                    ->title('Firma založena')
                    ->body($company->name)
                    ->send();
            });
    }
}
