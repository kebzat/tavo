<?php

namespace App\Support\Crm;

use App\Enums\Crm\ActivityOutcome;
use App\Enums\Crm\ActivityType;
use App\Enums\Crm\CompanyStatus;
use App\Filament\Tools\Resources\Companies\Pages\EditCompany;
use App\Models\Crm\Activity;
use App\Models\Crm\Company;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Koho jsme oslovili a jak zareagoval. Jen přehled, nic se tu neplní.
 *
 * Reakce se bere z výsledku zapsaných aktivit. Když ho nikdo nezapsal,
 * napoví stav firmy (Odpověděli, Hovor, Nabídka, Vyhráno, Nezájem).
 */
class OutreachLog
{
    /**
     * Oslovené firmy od posledního oslovení.
     *
     * @return Collection<int, array{name: string, url: string, first_at: Carbon, last_at: Carbon, touches: int, channel: string, reaction: string, reaction_color: string, status: string, status_color: string}>
     */
    public static function rows(int $limit = 200): Collection
    {
        $types = array_column(ActivityType::outreach(), 'value');

        return Company::query()
            ->whereHas('activities', fn ($query) => $query->whereIn('type', $types))
            ->withMax(['activities as last_outreach_at' => fn ($query) => $query->whereIn('type', $types)], 'happened_at')
            ->with(['activities' => fn ($query) => $query->latest('happened_at')])
            ->orderByDesc('last_outreach_at')
            ->limit($limit)
            ->get()
            ->map(function (Company $company) use ($types): array {
                $outreach = $company->activities->filter(fn (Activity $a): bool => in_array($a->type?->value, $types, true));
                [$reaction, $color] = self::reaction($company, $outreach->first()->happened_at);

                return [
                    'name' => $company->name,
                    'url' => EditCompany::getUrl(['record' => $company], panel: 'tools'),
                    'first_at' => $outreach->last()->happened_at,
                    'last_at' => $outreach->first()->happened_at,
                    'touches' => $outreach->count(),
                    'channel' => $outreach->first()->type->getLabel(),
                    'reaction' => $reaction,
                    'reaction_color' => $color,
                    'status' => $company->status?->getLabel() ?? '',
                    'status_color' => $company->status?->getColor() ?? 'gray',
                ];
            });
    }

    /** @return array{0: string, 1: string} popisek a barva štítku */
    private static function reaction(Company $company, Carbon $lastOutreach): array
    {
        $answer = $company->activities->first(
            fn (Activity $a): bool => in_array($a->outcome, [...ActivityOutcome::answered(), ActivityOutcome::Negative], true),
        );

        if ($answer !== null) {
            return [$answer->outcome->getLabel(), $answer->outcome->getColor()];
        }

        return match ($company->status) {
            CompanyStatus::Lost => ['Odmítnutí', 'danger'],
            CompanyStatus::Replied, CompanyStatus::Call, CompanyStatus::Proposal, CompanyStatus::Won => ['Odpověděli', 'success'],
            default => ['Bez reakce '.self::since($lastOutreach), 'gray'],
        };
    }

    private static function since(Carbon $at): string
    {
        $days = (int) $at->copy()->startOfDay()->diffInDays(now()->startOfDay());

        return match (true) {
            $days === 0 => '(dnes)',
            $days === 1 => '(1 den)',
            $days < 5 => "({$days} dny)",
            default => "({$days} dní)",
        };
    }
}
