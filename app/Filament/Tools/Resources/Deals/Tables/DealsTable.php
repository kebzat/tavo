<?php

namespace App\Filament\Tools\Resources\Deals\Tables;

use App\Enums\Crm\DealPackage;
use App\Enums\Crm\DealStage;
use App\Models\Crm\Deal;
use App\Models\User;
use App\Support\Crm\CsvExport;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DealsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['company', 'owner']))
            ->defaultSort('stage_changed_at', 'desc')
            ->persistFiltersInSession()
            ->columns([
                TextColumn::make('company.name')
                    ->label('Firma')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (Deal $record): string => $record->title)
                    ->wrap(),

                TextColumn::make('stage')->label('Fáze')->badge()->sortable(),

                TextColumn::make('package')->label('Balíček')->badge()->color('gray')->toggleable(),

                TextColumn::make('value_czk')
                    ->label('Hodnota')
                    ->money('CZK', locale: 'cs')
                    ->sortable()
                    ->summarize(Sum::make()->label('Celkem')->money('CZK', locale: 'cs'))
                    ->placeholder('—'),

                TextColumn::make('probability')->label('Pravděp.')->suffix(' %')->sortable()->toggleable(),

                TextColumn::make('owner.name')->label('Vede')->toggleable()->placeholder('—'),

                TextColumn::make('stage_changed_at')
                    ->label('Ve fázi')
                    ->formatStateUsing(fn (Deal $record): string => $record->daysInStage().' dní')
                    // Obchod, který se měsíc nehnul, potřebuje pozornost dřív
                    // než ten včerejší.
                    ->color(fn (Deal $record): ?string => $record->daysInStage() > 14 && ! $record->stage->isClosed() ? 'warning' : null)
                    ->sortable(),

                TextColumn::make('expected_close_at')->label('Uzavření')->date('j. n. Y')->toggleable()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('stage')->label('Fáze')->options(DealStage::class)->multiple(),
                SelectFilter::make('package')->label('Balíček')->options(DealPackage::class)->multiple(),
                SelectFilter::make('owner_id')
                    ->label('Vede')
                    ->options(fn (): array => User::orderBy('name')->pluck('name', 'id')->all()),
                Filter::make('open')
                    ->label('Jen rozjednané')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->open()),
            ])
            ->recordActions([EditAction::make()->label('')->iconButton()])
            ->headerActions([
                Action::make('export')
                    ->label('Export CSV')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->action(fn ($livewire) => self::export($livewire->getFilteredSortedTableQuery())),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->emptyStateHeading('Žádné obchody')
            ->emptyStateDescription('Obchod se zakládá na kartě firmy, jakmile je z oslovení konkrétní příležitost.');
    }

    public static function export(Builder $query)
    {
        $header = ['firma', 'obchod', 'balicek', 'faze', 'hodnota', 'pravdepodobnost', 'vazena_hodnota', 'vede', 'ocekavane_uzavreni', 'vyhrano', 'prohrano', 'duvod_prohry'];

        $rows = $query->with(['company', 'owner'])->get()->map(fn (Deal $deal): array => [
            $deal->company?->name,
            $deal->title,
            $deal->package,
            $deal->stage,
            $deal->value_czk,
            $deal->probability,
            (int) round($deal->weightedValue()),
            $deal->owner?->name,
            $deal->expected_close_at,
            $deal->won_at,
            $deal->lost_at,
            $deal->lost_reason,
        ]);

        return CsvExport::download('obchody-'.now()->format('Y-m-d').'.csv', $header, $rows);
    }
}
