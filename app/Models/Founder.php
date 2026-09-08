<?php

namespace App\Models;

use App\Support\ResponsiveImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Founder extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const MEDIA_PHOTO = 'photo';

    protected $guarded = [];

    protected $casts = [
        'tags' => 'array',
    ];

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order_column')->orderBy('id');
    }

    /** Zakladatelé, kterým se dá zavolat. Bez čísla se nabízet nemá smysl. */
    public function scopeCallable(Builder $query): Builder
    {
        return $query->whereNotNull('phone')->where('phone', '!=', '');
    }

    /**
     * Číslo, jak se píše na webu. Předvolbu +420 vynecháváme: Čech ji v čísle
     * nečte a bez ní se dvojice čísel pod sebou přehlédne líp. V odkazu
     * `tel:` samozřejmě zůstává, viz phoneHref().
     */
    public function phoneLabel(): string
    {
        return trim(preg_replace('/^\+420\s*/', '', (string) $this->phone));
    }

    /** Číslo ve tvaru pro href="tel:" — bez mezer, s předvolbou. */
    public function phoneHref(): string
    {
        return 'tel:'.preg_replace('/[^0-9+]/', '', (string) $this->phone);
    }

    public function registerMediaCollections(): void
    {
        // useDisk('public') — viz komentář v CaseStudy::registerMediaCollections().
        $this->addMediaCollection(self::MEDIA_PHOTO)->singleFile()->useDisk('public');
    }

    /**
     * Fotka i s rozměry a sadou zmenšenin pro `srcset`.
     *
     * @return array{src: string, srcset: ?string, width: ?int, height: ?int, alt: string}|null
     */
    public function photoImage(string $alt = ''): ?array
    {
        $media = $this->getFirstMedia(self::MEDIA_PHOTO);

        if (! $media) {
            return null;
        }

        return ResponsiveImage::make($media->getPathRelativeToRoot(), $alt);
    }
}
