<?php

namespace App\Filament\Tools\Resources\Companies\RelationManagers;

use App\Models\Crm\Contact;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    protected static ?string $title = 'Kontakty';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Jméno')
                ->maxLength(255)
                ->helperText('Z rešerše ho často neznáme, pole smí zůstat prázdné.'),

            TextInput::make('role')->label('Pozice')->maxLength(255),
            TextInput::make('email')->label('E-mail')->email()->maxLength(255),
            TextInput::make('phone')->label('Telefon')->tel()->maxLength(255),
            TextInput::make('linkedin_url')->label('LinkedIn')->url()->maxLength(255),

            Toggle::make('is_primary')
                ->label('Hlavní kontakt')
                ->helperText('Na něj míří šablony zpráv. Hlavní může být jen jeden.'),

            Textarea::make('notes')->label('Poznámka')->rows(2),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('is_primary', 'desc')
            ->columns([
                IconColumn::make('is_primary')
                    ->label('')
                    ->boolean()
                    ->trueIcon(Heroicon::Star)
                    ->falseIcon(Heroicon::OutlinedUser)
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->tooltip(fn (Contact $record): string => $record->is_primary ? 'Hlavní kontakt' : 'Kontakt'),

                TextColumn::make('name')
                    ->label('Jméno')
                    ->weight('bold')
                    ->description(fn (Contact $record): ?string => $record->role)
                    ->placeholder('bez jména'),

                TextColumn::make('email')
                    ->label('E-mail')
                    ->copyable()
                    ->copyMessage('E-mail zkopírován')
                    ->url(fn (Contact $record): ?string => $record->email ? 'mailto:'.$record->email : null)
                    ->placeholder('—'),

                TextColumn::make('phone')
                    ->label('Telefon')
                    ->copyable()
                    // Na mobilu je tohle nejrychlejší cesta k hovoru.
                    ->url(fn (Contact $record): ?string => $record->phone ? 'tel:'.preg_replace('~\s~', '', $record->phone) : null)
                    ->placeholder('—'),

                TextColumn::make('notes')->label('Poznámka')->limit(40)->toggleable()->placeholder('—'),
            ])
            ->headerActions([CreateAction::make()->label('Přidat kontakt')])
            ->recordActions([EditAction::make()->label('')->iconButton(), DeleteAction::make()->label('')->iconButton()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->emptyStateHeading('Žádný kontakt')
            ->emptyStateDescription('Bez e-mailu nebo telefonu se firmě neozveme — přidej první kontakt.');
    }
}
