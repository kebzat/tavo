<?php

namespace App\Enums\Crm;

use App\Enums\WorkArea;
use Filament\Support\Contracts\HasLabel;

/** Co konkrétně firmě prodáváme. */
enum DealPackage: string implements HasLabel
{
    case MigrationShoptet = 'migration_shoptet';
    case IntegrationPohodaCarrier = 'integration_pohoda_carrier';
    case MeasurementAudit = 'measurement_audit';
    case NewWebsite = 'new_website';
    case EshopRedesign = 'eshop_redesign';
    case Retainer = 'retainer';
    case Subcontracting = 'subcontracting';
    case Other = 'other';

    /**
     * Kdo zakázku fakturuje, když u obchodu není oblast vyplněná ručně.
     * Jiné nechává na obchodu.
     */
    public function area(): ?WorkArea
    {
        return match ($this) {
            self::MeasurementAudit => WorkArea::Marketing,
            self::Other => null,
            default => WorkArea::Web,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::MigrationShoptet => 'Migrace na Shoptet',
            self::IntegrationPohodaCarrier => 'Napojení Pohoda / dopravci',
            self::MeasurementAudit => 'Audit měření',
            self::NewWebsite => 'Nový web',
            self::EshopRedesign => 'Redesign e-shopu',
            self::Retainer => 'Průběžná správa',
            self::Subcontracting => 'Subdodávka pro agenturu',
            self::Other => 'Jiné',
        };
    }
}
