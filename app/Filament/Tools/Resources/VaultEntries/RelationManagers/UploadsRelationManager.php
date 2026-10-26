<?php

namespace App\Filament\Tools\Resources\VaultEntries\RelationManagers;

use App\Enums\VaultType;
use App\Models\VaultEntry;
use App\Models\VaultUpload;
use App\Support\Vault\FtpUploader;
use App\Support\Vault\VaultException;
use App\Support\Vault\VaultLock;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Soubory, které agent chce nahrát přes FTP: schválit, zahodit, vrátit. */
class UploadsRelationManager extends RelationManager
{
    protected static string $relationship = 'uploads';

    protected static ?string $title = 'Nahrání od agenta';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof VaultEntry && $ownerRecord->type === VaultType::Ftp;
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $pending = $ownerRecord->uploads()->where('status', VaultUpload::STATUS_PENDING)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        return 'warning';
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('description')
                    ->label('Co agent mění')
                    ->state(fn (VaultUpload $record): string => $record->description ?: 'Bez popisu')
                    ->description(fn (VaultUpload $record): string => count($record->files).' '.trans_choice('soubor|soubory|souborů', count($record->files)).': '
                        .collect($record->files)->take(3)->map(fn (array $file): string => basename($file['path']))->implode(', ').(count($record->files) > 3 ? '…' : ''))
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Stav')
                    ->badge()
                    ->formatStateUsing(fn (VaultUpload $record): string => $record->statusLabel())
                    ->color(fn (VaultUpload $record): string => $record->statusColor())
                    ->description(fn (VaultUpload $record): ?string => $record->decided_by)
                    ->tooltip(fn (VaultUpload $record): ?string => $record->error),
                TextColumn::make('created_at')->label('Kdy')->since()->color('gray')->visibleFrom('xl'),
            ])
            ->recordActions([
                Action::make('files')
                    ->label('Soubory')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->color('gray')
                    ->iconButton()
                    ->tooltip('Které soubory')
                    ->modalHeading('Soubory k nahrání')
                    ->modalDescription(fn (VaultUpload $record): string => $record->description ?: 'Agent nenapsal, co mění.')
                    ->modalContent(fn (VaultUpload $record) => view('filament.tools.vault-upload-files', ['upload' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Zavřít'),
                Action::make('apply')
                    ->label('Nahrát na web')
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->color('success')
                    ->visible(fn (VaultUpload $record): bool => $record->status === VaultUpload::STATUS_PENDING)
                    ->requiresConfirmation()
                    ->modalDescription('Soubory se nahrají na web přes FTP. Co tam bylo předtím, se uloží a jde to vrátit tlačítkem Vrátit.')
                    ->action(fn (VaultUpload $record) => $this->run(fn (FtpUploader $uploader) => $uploader->apply($record, VaultLock::actor()), 'Nahráno na web')),
                Action::make('discard')
                    ->label('Zahodit')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('gray')
                    ->iconButton()
                    ->tooltip('Zahodit, na web nic nepůjde')
                    ->visible(fn (VaultUpload $record): bool => $record->status === VaultUpload::STATUS_PENDING)
                    ->requiresConfirmation()
                    ->action(fn (VaultUpload $record) => $this->run(fn (FtpUploader $uploader) => $uploader->discard($record, VaultLock::actor()), 'Zahozeno')),
                Action::make('revert')
                    ->label('Vrátit')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('danger')
                    ->visible(fn (VaultUpload $record): bool => $record->status === VaultUpload::STATUS_APPLIED)
                    ->requiresConfirmation()
                    ->modalDescription('Na web se vrátí soubory, které tam byly před nahráním. Soubory, které agent přidal, se smažou.')
                    ->action(fn (VaultUpload $record) => $this->run(fn (FtpUploader $uploader) => $uploader->revert($record, VaultLock::actor()), 'Vráceno')),
            ])
            ->emptyStateHeading('Agent zatím nic nenahrával')
            ->emptyStateDescription(fn (): string => $this->getOwnerRecord()->agentAllows('approve')
                ? 'Až bude chtít něco nahrát, objeví se to tady.'
                : 'U tohoto přístupu agent nahrávat nesmí („Co smí agent“ výš).');
    }

    private function run(callable $work, string $done): void
    {
        try {
            app()->call($work);
        } catch (VaultException $e) {
            Notification::make()->danger()->title('Nepovedlo se')->body($e->getMessage())->persistent()->send();

            return;
        }

        Notification::make()->success()->title($done)->send();
    }
}
