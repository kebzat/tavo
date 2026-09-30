<?php

namespace App\Filament\Tools\Actions\Ads;

use App\Models\Client;
use App\Models\TimeEntry;
use App\Support\Ads\Billing;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;

/**
 * Formulář zápisu času. Délka se píše jako „1:30“, „1,5“ nebo „45m“,
 * ať se nemusí přepočítávat na minuty.
 */
class LogTimeAction
{
    /** @return list<Component|Field> */
    public static function schema(?Client $client = null): array
    {
        return [
            Select::make('client_id')
                ->label('Klient')
                ->options(fn (): array => Client::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->required()
                ->visible($client === null),
            Grid::make(2)->schema([
                DatePicker::make('worked_on')->label('Den')->native(false)->displayFormat('j. n. Y')->default(now())->required(),
                TextInput::make('duration')
                    ->label('Jak dlouho')
                    ->placeholder('1:30')
                    ->helperText('1:30, 1,5 nebo 45m')
                    ->required()
                    ->rules([fn (): Closure => function (string $attribute, $value, Closure $fail): void {
                        $minutes = Billing::parseMinutes((string) $value);

                        if ($minutes === null || $minutes <= 0 || $minutes > 24 * 60) {
                            $fail('Zapište délku jako 1:30, 1,5 nebo 45m.');
                        }
                    }]),
            ]),
            TextInput::make('description')->label('Co se dělalo')->required()->maxLength(255)->placeholder('Nové kreativy do prospectingu, úprava cílení'),
            Toggle::make('billable')->label('Počítat do fakturace')->default(true)->helperText('Vypněte u práce, kterou klientovi neúčtujeme (interní porada, oprava naší chyby).'),
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function create(array $data, ?Client $client = null): TimeEntry
    {
        return TimeEntry::create([
            'client_id' => $client?->getKey() ?? $data['client_id'],
            'user_id' => auth()->id(),
            'worked_on' => $data['worked_on'],
            'minutes' => Billing::parseMinutes((string) $data['duration']),
            'description' => $data['description'],
            'billable' => (bool) ($data['billable'] ?? true),
        ]);
    }
}
