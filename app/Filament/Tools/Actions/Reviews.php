<?php

namespace App\Filament\Tools\Actions;

use App\Models\Review;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * „Pavel – zkontrolováno“, „Tom – zkontrolováno“ u všeho, co odchází
 * klientovi. Model musí používat App\Models\Concerns\HasReviews.
 *
 * Svou kontrolu odškrtne každý kliknutím do svého sloupce nebo tlačítkem
 * na detailu. Cizí sloupec je jen pro čtení.
 */
final class Reviews
{
    /**
     * Sloupec pro každého kontrolujícího. Tabulka k nim potřebuje
     * ->with('reviews'), jinak se kontroly načítají po řádcích.
     *
     * @return list<IconColumn>
     */
    public static function columns(): array
    {
        return User::reviewers()->get()
            ->map(fn (User $user): IconColumn => IconColumn::make('review_'.$user->getKey())
                ->label($user->name.' – zkontrolováno')
                ->alignCenter()
                ->state(fn (Model $record): string => self::state($record->reviewBy($user)))
                ->icon(fn (string $state): Heroicon => match ($state) {
                    'done' => Heroicon::OutlinedCheckCircle,
                    'outdated' => Heroicon::OutlinedExclamationCircle,
                    default => Heroicon::OutlinedMinusCircle,
                })
                ->color(fn (string $state): string => match ($state) {
                    'done' => 'success',
                    'outdated' => 'warning',
                    default => 'gray',
                })
                ->tooltip(fn (Model $record): string => self::tooltip($record->reviewBy($user), $user))
                ->disabledClick(fn (): bool => Auth::id() !== $user->getKey())
                ->action(fn (Model $record) => self::toggle($record)))
            ->values()
            ->all();
    }

    /** Tlačítko na detailu: odškrtne nebo zruší kontrolu přihlášeného. */
    public static function action(): Action
    {
        return Action::make('toggleReview')
            ->label(fn (Model $record): string => $record->isReviewedBy(Auth::user()) ? 'Zkontrolováno' : 'Označit jako zkontrolované')
            ->icon(fn (Model $record): Heroicon => $record->isReviewedBy(Auth::user()) ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedEye)
            ->color(fn (Model $record): string => $record->isReviewedBy(Auth::user()) ? 'success' : 'gray')
            ->tooltip(fn (Model $record): string => $record->reviewSummary())
            ->visible(fn (): bool => (bool) Auth::user()?->is_reviewer)
            ->action(fn (Model $record) => self::toggle($record));
    }

    /** Přehled kontrol do sekce Sdílení ve formuláři. */
    public static function entry(): TextEntry
    {
        return TextEntry::make('reviews_summary')
            ->label('Kontrola před odesláním')
            ->visible(fn ($operation): bool => $operation === 'edit')
            ->state(fn (?Model $record): string => $record?->reviewSummary() ?: 'Nikdo není nastavený jako kontrolující.')
            ->color(fn (?Model $record): string => $record && $record->missingReviewers()->isEmpty() ? 'success' : 'warning')
            ->columnSpanFull();
    }

    /** „ Zatím nezkontroloval: Pavel.“ do potvrzení odeslání. Prázdné, když je vše zkontrolované. */
    public static function warning(Model $record): string
    {
        $missing = $record->missingReviewers();

        return $missing->isEmpty() ? '' : ' Zatím nezkontroloval: '.$missing->pluck('name')->implode(', ').'.';
    }

    private static function toggle(Model $record): void
    {
        $done = $record->toggleReview(Auth::user());

        Notification::make()
            ->success()
            ->title($done ? 'Označeno jako zkontrolované' : 'Kontrola zrušena')
            ->send();
    }

    private static function state(?Review $review): string
    {
        return match (true) {
            $review === null => 'missing',
            $review->isOutdated() => 'outdated',
            default => 'done',
        };
    }

    private static function tooltip(?Review $review, User $user): string
    {
        $text = match (true) {
            $review === null => 'Zatím nezkontrolováno',
            $review->isOutdated() => 'Zkontrolováno '.$review->reviewed_at->format('j. n. H:i').', pak se obsah změnil',
            default => 'Zkontrolováno '.$review->reviewed_at->format('j. n. H:i'),
        };

        return Auth::id() === $user->getKey() ? $text.' · kliknutím přepnete' : $text;
    }
}
