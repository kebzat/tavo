<?php

namespace App\Enums\Crm;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Sloupce v pipeline. Pořadí případů odpovídá pořadí sloupců. */
enum DealStage: string implements HasColor, HasLabel
{
    case Lead = 'lead';
    case Contacted = 'contacted';
    case Replied = 'replied';
    case Call = 'call';
    case ProposalSent = 'proposal_sent';
    case Negotiation = 'negotiation';
    case Won = 'won';
    case Lost = 'lost';

    public function getLabel(): string
    {
        return match ($this) {
            self::Lead => 'Příležitost',
            self::Contacted => 'Osloveno',
            self::Replied => 'Odpověděli',
            self::Call => 'Hovor',
            self::ProposalSent => 'Nabídka odeslána',
            self::Negotiation => 'Vyjednávání',
            self::Won => 'Vyhráno',
            self::Lost => 'Prohráno',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Lead => 'gray',
            self::Contacted => 'info',
            self::Replied => 'info',
            self::Call => 'warning',
            self::ProposalSent => 'primary',
            self::Negotiation => 'primary',
            self::Won => 'success',
            self::Lost => 'danger',
        };
    }

    /**
     * Výchozí pravděpodobnost uzavření. Předvyplní se při změně fáze,
     * ale uživatel ji smí přepsat — u konkrétního obchodu víme víc než tabulka.
     */
    public function defaultProbability(): int
    {
        return match ($this) {
            self::Lead => 5,
            self::Contacted => 10,
            self::Replied => 25,
            self::Call => 40,
            self::ProposalSent => 50,
            self::Negotiation => 70,
            self::Won => 100,
            self::Lost => 0,
        };
    }

    /** Uzavřené fáze v kanbanu nezabírají místo mezi rozpracovanými. */
    public function isClosed(): bool
    {
        return $this === self::Won || $this === self::Lost;
    }

    /**
     * Fáze, které kanban zobrazuje jako sloupce. Prohráno mezi ně nepatří —
     * jinak by sloupec za pár měsíců přerostl všechny ostatní.
     *
     * @return array<int, self>
     */
    public static function boardColumns(): array
    {
        return [
            self::Lead,
            self::Contacted,
            self::Replied,
            self::Call,
            self::ProposalSent,
            self::Negotiation,
            self::Won,
        ];
    }
}
