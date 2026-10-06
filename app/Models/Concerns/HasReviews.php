<?php

namespace App\Models\Concerns;

use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Kontrola před odesláním klientovi. Každý kontrolující (users.is_reviewer)
 * si odškrtne svou.
 *
 * Změna obsahu po kontrole ji označí jako zastaralou, kromě kontroly toho,
 * kdo změnu udělal: svou úpravu zná. Za obsah se počítá každý sloupec mimo
 * REVIEW_IGNORED, takže zobrazení klientem (saveQuietly) ani odeslání
 * kontrolu neshodí. Úpravy v napojených tabulkách (položky checklistu)
 * se nehlídají.
 */
trait HasReviews
{
    /** Sloupce, jejichž změna kontrolu neruší. */
    private const REVIEW_IGNORED = [
        'updated_at', 'is_public', 'is_teaser', 'slug', 'public_token',
        'view_count', 'first_viewed_at', 'last_viewed_at',
        'status', 'sent_at', 'sent_to', 'ai_status', 'ai_note',
    ];

    public static function bootHasReviews(): void
    {
        static::updated(function (self $model): void {
            if (array_diff(array_keys($model->getChanges()), self::REVIEW_IGNORED) === [] || ! self::reviewsTableExists()) {
                return;
            }

            $model->reviews()
                ->whereNull('outdated_at')
                ->when(Auth::id(), fn ($query, $userId) => $query->where('user_id', '!=', $userId))
                ->update(['outdated_at' => now()]);

            $model->unsetRelation('reviews');
        });
    }

    /**
     * Starší datové migrace upravují checklisty a audity dřív, než tabulka
     * kontrol vznikne. Kladnou odpověď si pamatujeme, zápornou ne: během
     * migrací tabulka přibude.
     */
    private static function reviewsTableExists(): bool
    {
        static $exists = false;

        return $exists = $exists || Schema::hasTable('reviews');
    }

    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    public function reviewBy(User $user): ?Review
    {
        return $this->reviews->firstWhere('user_id', $user->getKey());
    }

    public function isReviewedBy(User $user): bool
    {
        $review = $this->reviewBy($user);

        return $review !== null && ! $review->isOutdated();
    }

    /** Odškrtne kontrolu, nebo ji zruší, když už platí. */
    public function toggleReview(User $user): bool
    {
        if ($this->isReviewedBy($user)) {
            $this->reviews()->where('user_id', $user->getKey())->delete();
            $this->unsetRelation('reviews');

            return false;
        }

        $this->reviews()->updateOrCreate(
            ['user_id' => $user->getKey()],
            ['reviewed_at' => now(), 'outdated_at' => null],
        );
        $this->unsetRelation('reviews');

        return true;
    }

    /**
     * Kontrolující, od kterých chybí platná kontrola.
     *
     * @return Collection<int, User>
     */
    public function missingReviewers(): Collection
    {
        return User::reviewers()->get()->reject(fn (User $user): bool => $this->isReviewedBy($user))->values();
    }

    /** „Pavel: 6. 10. 14:20 · Tom: chybí“ */
    public function reviewSummary(): string
    {
        return User::reviewers()->get()
            ->map(function (User $user): string {
                $review = $this->reviewBy($user);

                return $user->name.': '.match (true) {
                    $review === null => 'chybí',
                    $review->isOutdated() => 'prošel starší verzi ('.$review->reviewed_at->format('j. n. H:i').')',
                    default => 'zkontrolováno '.$review->reviewed_at->format('j. n. H:i'),
                };
            })
            ->implode(' · ');
    }
}
