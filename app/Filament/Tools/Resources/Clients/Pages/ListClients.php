<?php

namespace App\Filament\Tools\Resources\Clients\Pages;

use App\Filament\Tools\Resources\Clients\ClientResource;
use App\Support\ClickUp\ClickUp;
use App\Support\ClickUp\ClientImport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class ListClients extends ListRecords
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Složka v ClickUpu = klient: založí ho s projektem agenta a archiv naplní tím, co ve složce je.
            Action::make('clickup')
                ->label('Načíst z ClickUpu')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->visible(fn (): bool => app(ClickUp::class)->configured())
                ->modalHeading('Klienti ze složek v ClickUpu')
                ->modalDescription('Každá vybraná složka bude klient: napojený na agenta (@agent u úkolů ze všech jejích seznamů), s vlastním projektem agenta bez kódu a s archivem. Do archivu se načtou všechny seznamy s úkoly, popisy, komentáři a přílohami a Docs ze složky. Prázdné se vynechají. Načítání běží na pozadí, velká složka i několik minut.')
                ->modalSubmitActionLabel('Založit a načíst')
                ->schema([
                    CheckboxList::make('folders')
                        ->label('Složky')
                        ->options(fn (): array => collect($this->clickUpFolders())
                            ->reject(fn (array $folder): bool => $folder['linked'])
                            ->mapWithKeys(fn (array $folder): array => [$folder['id'] => $folder['space'].' › '.$folder['name']])
                            ->all())
                        ->descriptions(fn (): array => collect($this->clickUpFolders())
                            ->reject(fn (array $folder): bool => $folder['linked'])
                            ->mapWithKeys(fn (array $folder): array => [$folder['id'] => $folder['lists'].' '.($folder['lists'] === 1 ? 'seznam' : ($folder['lists'] >= 2 && $folder['lists'] <= 4 ? 'seznamy' : 'seznamů'))
                                .($folder['client'] ? ' · klient „'.$folder['client'].'“ už je, jen se napojí a doplní archiv' : ' · nový klient')])
                            ->all())
                        ->bulkToggleable()
                        ->searchable()
                        ->required()
                        ->helperText(fn (): string => collect($this->clickUpFolders())->contains('linked', true)
                            ? 'Složky, které už klienta mají, tu nejsou. Znovu je načtete u klienta (Načíst z ClickUpu znovu).'
                            : ''),
                ])
                ->action(function (array $data): void {
                    $folders = collect($this->clickUpFolders())->keyBy('id');
                    $import = app(ClientImport::class);
                    $created = [];

                    foreach ($data['folders'] as $id) {
                        if ($folder = $folders->get($id)) {
                            $created[] = $import->create($folder, auth()->user()?->name)->name;
                        }
                    }

                    Cache::forget('clickup.folders');

                    Notification::make()
                        ->success()
                        ->title(count($created) === 1 ? 'Klient '.$created[0].' je založený' : 'Založeno klientů: '.count($created))
                        ->body('Archiv se plní z ClickUpu na pozadí, průběh je u klienta v Archivu. '.Str::limit(implode(', ', $created), 300))
                        ->send();
                }),

            CreateAction::make(),
        ];
    }

    /**
     * Složky z ClickUpu na pět minut, formulář se překresluje.
     *
     * @return list<array{id: string, name: string, space: string, lists: int, client: ?string, linked: bool}>
     */
    private function clickUpFolders(): array
    {
        try {
            return Cache::remember('clickup.folders', now()->addMinutes(5), fn (): array => app(ClientImport::class)->folders());
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }
}
