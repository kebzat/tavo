<?php

namespace App\Filament\Tools\Actions;

use App\Enums\Crm\ActivityOutcome;
use App\Enums\Crm\ActivityType;
use App\Models\Crm\Activity;
use App\Models\Crm\Company;
use App\Settings\CrmSettings;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Zápis toho, co jsme s firmou udělali.
 *
 * Nejpoužívanější akce celého CRM, proto žije zvlášť a nabízí se všude, kde
 * je firma po ruce — v seznamu, na detailu i v přehledu „Dnes". Formulář má
 * jediné povinné pole navíc k předvyplněným, takže záznam je otázka tří kliků.
 *
 * Firma se do akce dostane dvěma cestami: v tabulce firem ji Filament podá
 * jako `$record`, jinde (přehled „Dnes", časová osa) se předá v `arguments`.
 * Obojí řeší resolveCompany().
 */
class LogActivityAction
{
    public static function make(string $name = 'logActivity'): Action
    {
        return Action::make($name)
            ->label('Zalogovat aktivitu')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->modalHeading('Zalogovat aktivitu')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel('Uložit')
            ->schema(self::schema())
            ->action(function (array $data, array $arguments, ?Model $record): void {
                $company = self::resolveCompany($arguments, $record);

                self::log($company, $data);

                // Termín dopočítá observer na vlastní instanci firmy, takže
                // ta zdejší o novou hodnotu ještě neví.
                $company->refresh();

                Notification::make()
                    ->success()
                    ->title('Aktivita zapsána')
                    ->body($company->next_action_at
                        ? 'Další krok: '.$company->next_action_at->format('j. n. Y')
                        : 'Bez naplánovaného follow-upu.')
                    ->send();
            });
    }

    /**
     * Firma, které se aktivita zapisuje.
     *
     * Tabulka firem podává záznam sama, ostatní místa ho posílají v arguments.
     * Kdyby chybělo obojí, je to chyba v zapojení akce, ne stav, který by se
     * dal rozumně ošetřit.
     */
    public static function resolveCompany(array $arguments, ?Model $record): Company
    {
        if ($record instanceof Company) {
            return $record;
        }

        return Company::findOrFail($arguments['company'] ?? null);
    }

    /**
     * Pole formuláře. Sdílí je i akce „Použít šablonu", aby se follow-up
     * plánoval v celém CRM stejně.
     *
     * @return array<int, mixed>
     */
    public static function schema(): array
    {
        return [
            ToggleButtons::make('type')
                ->label('Typ')
                ->options(ActivityType::class)
                ->default(ActivityType::Email)
                ->inline()
                ->required(),

            TextInput::make('subject')
                ->label('Předmět')
                ->required()
                ->maxLength(255)
                ->placeholder('Co jsme udělali'),

            Textarea::make('body')
                ->label('Text')
                ->rows(5)
                ->placeholder('Co jsme poslali nebo o čem jsme mluvili.'),

            DateTimePicker::make('happened_at')
                ->label('Kdy')
                ->seconds(false)
                ->default(now())
                ->required(),

            ToggleButtons::make('follow_up')
                ->label('Ozvat se znovu')
                ->options(self::followUpOptions())
                ->default('none')
                ->inline()
                ->live()
                ->required(),

            DateTimePicker::make('follow_up_at')
                ->label('Datum follow-upu')
                ->seconds(false)
                ->default(now()->addDays(3)->setTime(9, 0))
                ->requiredIf('follow_up', 'custom')
                ->visible(fn ($get): bool => $get('follow_up') === 'custom'),

            Select::make('outcome')
                ->label('Výsledek')
                ->options(ActivityOutcome::class)
                ->native(false)
                ->placeholder('Zatím nevíme'),
        ];
    }

    /**
     * Nabídka odkladů. Počty dní si řídí nastavení CRM — jak se mění tempo
     * oslovování, mění se i to, co má smysl nabízet jedním kliknutím.
     *
     * @return array<string, string>
     */
    public static function followUpOptions(): array
    {
        $options = ['none' => 'Není třeba'];

        foreach (app(CrmSettings::class)->follow_up_days as $days) {
            $options[(string) $days] = 'za '.$days.' '.self::dayWord((int) $days);
        }

        return $options + ['custom' => 'Vlastní datum'];
    }

    /** Zápis aktivity. Termín dalšího kroku dopočítá observer. */
    public static function log(Company $company, array $data): Activity
    {
        return $company->activities()->create([
            'user_id' => Auth::id(),
            'deal_id' => $data['deal_id'] ?? null,
            'contact_id' => $data['contact_id'] ?? null,
            'type' => $data['type'],
            'subject' => $data['subject'],
            'body' => $data['body'] ?? null,
            'happened_at' => $data['happened_at'] ?? now(),
            'follow_up_at' => self::followUpDate($data),
            'outcome' => $data['outcome'] ?? null,
        ]);
    }

    /** Přepočet volby „za 3 dny" na konkrétní termín. */
    private static function followUpDate(array $data): ?Carbon
    {
        $choice = $data['follow_up'] ?? 'none';

        if ($choice === 'none') {
            return null;
        }

        if ($choice === 'custom') {
            return isset($data['follow_up_at']) ? Carbon::parse($data['follow_up_at']) : null;
        }

        // Follow-up padne na ráno. Připomínka na 23:40 by v přehledu „Dnes"
        // visela celý den jako nesplněná a v souhrnu by dorazila pozdě.
        return now()->addDays((int) $choice)->setTime(9, 0);
    }

    /** Skloňování po číslovce: 1 den, 2 až 4 dny, 5 a víc dní. */
    public static function dayWord(int $days): string
    {
        return match (true) {
            $days === 1 => 'den',
            $days < 5 => 'dny',
            default => 'dní',
        };
    }
}
