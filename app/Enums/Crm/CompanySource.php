<?php

namespace App\Enums\Crm;

use Filament\Support\Contracts\HasLabel;

/** Odkud firma přišla. Podle toho se v přehledu pozná, který kanál sype. */
enum CompanySource: string implements HasLabel
{
    case Research = 'research';
    case ShoptetDemands = 'shoptet_demands';
    case Webtrh = 'webtrh';
    case NaVolneNoze = 'navolnenoze';
    case Upgates = 'upgates';
    case Referral = 'referral';
    case InboundForm = 'inbound_form';
    case Linkedin = 'linkedin';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Research => 'Vlastní rešerše',
            self::ShoptetDemands => 'Shoptet poptávky',
            self::Webtrh => 'Webtrh',
            self::NaVolneNoze => 'Na volné noze',
            self::Upgates => 'Upgates',
            self::Referral => 'Doporučení',
            self::InboundForm => 'Formulář na webu',
            self::Linkedin => 'LinkedIn',
            self::Other => 'Jiné',
        };
    }
}
