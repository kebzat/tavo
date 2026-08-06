<?php

namespace App\Filament\Tools\Resources\Checklists\Actions;

use App\Filament\Tools\Resources\Checklists\Pages\EditChecklist;
use App\Models\Checklist;
use App\Models\Client;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Založí klientský checklist podle šablony. Jádro celého nástroje —
 * odsud vzniká každý nový checklist, ruční zakládání sekcí je výjimka.
 */
class CreateFromTemplateAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'createFromTemplate';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Vytvořit z šablony')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->modalHeading('Nový checklist ze šablony')
            ->modalSubmitActionLabel('Vytvořit')
            ->schema([
                Select::make('client_id')
                    ->label('Klient')
                    ->options(fn (): array => Client::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->required(),

                TextInput::make('name')
                    ->label('Název')
                    ->required()
                    ->default(fn (Checklist $record): string => $record->name),
            ])
            ->action(function (Checklist $record, array $data) {
                $client = Client::find($data['client_id']);
                $copy = $record->duplicateFor($client, $data['name']);

                Notification::make()
                    ->success()
                    ->title('Checklist vytvořen')
                    ->body('Zkopírovalo se '.$copy->items()->count().' položek.')
                    ->send();

                return redirect(EditChecklist::getUrl(['record' => $copy]));
            });
    }
}
