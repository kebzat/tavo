<?php

namespace App\Filament\Tools\Resources\MessageTemplates\Schemas;

use App\Enums\Crm\TemplateChannel;
use App\Support\Crm\TemplateRenderer;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MessageTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Šablona')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Název')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Interní, pozná se podle něj v roletce.'),

                    Select::make('channel')
                        ->label('Kanál')
                        ->options(TemplateChannel::class)
                        ->default(TemplateChannel::Email)
                        ->native(false)
                        ->live()
                        ->required(),

                    TextInput::make('subject')
                        ->label('Předmět')
                        ->maxLength(255)
                        ->columnSpanFull()
                        // Osnova hovoru se nikam neposílá, předmět by v ní byl
                        // jen prázdné pole navíc.
                        ->visible(fn ($get): bool => self::channel($get('channel'))?->hasSubject() ?? true),

                    Textarea::make('body')
                        ->label('Text')
                        ->rows(10)
                        ->required()
                        ->columnSpanFull()
                        ->helperText('Použitelné zástupné texty: '.implode(', ', array_keys(TemplateRenderer::PLACEHOLDERS)).'. Dosadí se údaje z karty firmy; prázdné hodnoty se z textu odstraní i s okolní interpunkcí.'),

                    Toggle::make('is_active')
                        ->label('Aktivní')
                        ->default(true)
                        ->helperText('Neaktivní šablona se v nabídce u firmy neukáže.'),
                ]),
        ]);
    }

    private static function channel(mixed $value): ?TemplateChannel
    {
        return $value instanceof TemplateChannel ? $value : TemplateChannel::tryFrom((string) $value);
    }
}
