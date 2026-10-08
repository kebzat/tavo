<?php

namespace App\Filament\Tools\Actions\Ads;

use App\Enums\WorkArea;
use App\Filament\Tools\Resources\Clients\RelationManagers\TasksRelationManager;
use App\Models\Client;
use App\Models\ClientTask;
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
use Filament\Schemas\Components\Utilities\Get;

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
                ->live()
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
            Select::make('task_id')
                ->label('Úkol')
                ->options(fn (Get $get): array => self::taskOptions($client?->getKey() ?? $get('client_id'), $get('task_id')))
                ->searchable()
                ->live()
                ->createOptionForm(fn (Get $get): array => TasksRelationManager::fields($client ?? Client::find($get('client_id'))))
                ->createOptionUsing(fn (array $data, Get $get): int => ClientTask::create($data + ['client_id' => $client?->getKey() ?? $get('client_id')])->getKey())
                ->helperText('Klient na přehledu vidí čas sečtený po úkolech. Bez úkolu se započte jen do čerpání paušálu nahoře.'),
            Select::make('area')
                ->label('Oblast')
                ->options(WorkArea::class)
                ->default(fn (Get $get): WorkArea => ($client ?? Client::find($get('client_id')))?->retainers->first()?->area ?? WorkArea::Web)
                ->visible(fn (Get $get): bool => blank($get('task_id'))),
            TextInput::make('description')->label('Co se dělalo')->required()->maxLength(255)->placeholder('Nové kreativy do prospectingu, úprava cílení')->helperText('Pro nás, klient zápis nevidí.'),
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
        ] + self::attributes($data));
    }

    /**
     * Úkol, oblast, popis a fakturace ze záznamu formuláře. Oblast se u úkolu
     * bere z něj, ať se zápis a úkol nerozejdou.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function attributes(array $data): array
    {
        $task = filled($data['task_id'] ?? null) ? ClientTask::find($data['task_id']) : null;

        return [
            'task_id' => $task?->getKey(),
            'area' => $task?->area ?? (filled($data['area'] ?? null) ? $data['area'] : null),
            'description' => $data['description'],
            'billable' => (bool) ($data['billable'] ?? true),
        ];
    }

    /**
     * Otevřené úkoly klienta, k nim i právě vybraný (u úpravy starého zápisu
     * může být už hotový).
     *
     * @return array<int, string>
     */
    private static function taskOptions(mixed $clientId, mixed $selected): array
    {
        if (blank($clientId)) {
            return [];
        }

        return ClientTask::query()
            ->where('client_id', $clientId)
            ->where(fn ($query) => $query->open()->when(filled($selected), fn ($query) => $query->orWhere('id', $selected)))
            ->orderBy('title')
            ->pluck('title', 'id')
            ->all();
    }
}
