<?php

namespace App\Observers\Crm;

use App\Enums\Crm\CompanyStatus;
use App\Models\Crm\Activity;

/**
 * Aktivita je jediné místo, kde se v CRM doopravdy pracuje. Aby stav firmy
 * nebylo nutné udržovat ručně (a tedy zapomínat), odvozuje se z aktivit tady.
 *
 * Řeší dvě věci:
 *  1. `next_action_at` a `last_activity_at` na firmě — vždy přepočet z dat,
 *     nikdy inkrementální úprava, ať sedí i po smazání nebo posunu aktivity.
 *  2. posun stavu z „Nová" na „Osloveno" po prvním kontaktu.
 */
class ActivityObserver
{
    public function saved(Activity $activity): void
    {
        $company = $activity->company;

        if ($company === null) {
            return;
        }

        // Firma se posouvá jen z výchozího stavu. Dál už o stavu rozhoduje
        // člověk — automat by přepsal „Nabídka" zpátky na „Osloveno" ve chvíli,
        // kdy si k obchodu doděláme poznámku.
        if ($company->status === CompanyStatus::New && $activity->type->isOutreach()) {
            $company->forceFill(['status' => CompanyStatus::Contacted])->saveQuietly();
        }

        $company->recalculateNextAction();
    }

    public function deleted(Activity $activity): void
    {
        $activity->company?->recalculateNextAction();
    }
}
