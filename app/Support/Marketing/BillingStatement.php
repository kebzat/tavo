<?php

namespace App\Support\Marketing;

use App\Models\TimeEntry;
use App\Support\Ads\Billing;
use App\Support\Ads\Format;
use Illuminate\Support\Str;

/**
 * Podklad k vyúčtování hodin klientovi, tak jak ho chce (karta klienta →
 * Vyúčtování hodin): jen součet, po úkolech, nebo každý zápis s datem.
 *
 * Popisy zápisů času jsou interní a ven nejdou, stejně jako na přehledu
 * pro klienta. Zápis se pojmenuje úkolem. Odesílá se vždy ručně.
 */
final class BillingStatement
{
    public function __construct(private readonly Billing $billing) {}

    public static function for(Billing $billing): self
    {
        return new self($billing);
    }

    public function mode(): string
    {
        return $this->preferences()['mode'] ?? 'email';
    }

    public function modeLabel(): string
    {
        return match ($this->mode()) {
            'invoice' => 'do poznámky na faktuře',
            'none' => 'klient vyúčtování nechce, stačí faktura',
            default => 'e-mailem zvlášť',
        };
    }

    /** @return list<string> */
    public function recipients(): array
    {
        $configured = collect(explode(',', (string) ($this->preferences()['recipients'] ?? '')))
            ->map(fn (string $email): string => trim($email))
            ->filter(fn (string $email): bool => (bool) filter_var($email, FILTER_VALIDATE_EMAIL));

        return ($configured->isNotEmpty() ? $configured : collect([$this->billing->client->contact_email])->filter())->values()->all();
    }

    public function note(): ?string
    {
        return $this->preferences()['note'] ?? null;
    }

    public function subject(): string
    {
        return 'Vyúčtování hodin za '.$this->billing->month->translatedFormat('F Y').' · '.$this->billing->client->name;
    }

    public function text(): string
    {
        $billing = $this->billing;
        $lines = [
            'Dobrý den,',
            '',
            'posíláme přehled práce za '.$billing->month->translatedFormat('F Y').($billing->area ? ' ('.Str::lower($billing->area->getLabel()).')' : '').'.',
            '',
        ];

        $detail = $this->preferences()['detail'] ?? 'tasks';
        $entries = TimeEntry::query()->with('task')->whereKey($billing->entryIds)->orderBy('worked_on')->get();

        if ($detail === 'tasks') {
            foreach ($entries->groupBy(fn (TimeEntry $entry): string => $entry->task?->title ?? 'Další práce') as $title => $group) {
                $lines[] = '- '.$title.': '.Billing::formatMinutes((int) $group->sum('minutes'));
            }
            $lines[] = '';
        }

        if ($detail === 'entries') {
            foreach ($entries as $entry) {
                $lines[] = '- '.$entry->worked_on->format('j. n.').' · '.($entry->task?->title ?? 'Další práce').': '.Billing::formatMinutes((int) $entry->minutes);
            }
            $lines[] = '';
        }

        $lines[] = 'Celkem odpracováno: '.Format::number($billing->billableHours, 1).' h'
            .($billing->includedHours > 0 ? ', v paušálu je '.Format::number($billing->includedHours, 1).' h' : '').'.';

        if ($billing->extraHours() > 0) {
            $lines[] = 'Nad rámec paušálu: '.Format::number($billing->extraHours(), 1).' h po '.Format::money($billing->hourlyRate).' = '.Format::money($billing->extraAmount()).'.';
        }

        $lines[] = 'Celkem k fakturaci: '.Format::money($billing->total()).' bez DPH.';
        $lines[] = '';
        $lines[] = 'Kdyby vám něco nesedělo, napište nám.';

        return implode("\n", $lines);
    }

    /** @return array<string, string> */
    private function preferences(): array
    {
        return array_filter($this->billing->client->billing_statement ?? [], 'filled');
    }
}
