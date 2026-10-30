<?php

namespace Lomkit\Rest\Tests\Fixtures\Publishing;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Unguarded]
#[UseFactory(PostFactory::class)]
#[UsePolicy(OpenPublishingPolicy::class)]
class Post extends Model
{
    use HasFactory;

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    #[Scope]
    protected function publishedSince(Builder $query, CarbonImmutable $since): void
    {
        $query->where('published_on', '>=', $since);
    }

    #[Scope]
    protected function popular(Builder $query, int $minViews): void
    {
        $query->where('views', '>=', $minViews);
    }

    #[Scope]
    protected function viewedBetween(Builder $query, int $min, ?int $max): void
    {
        $query->where('views', '>=', $min);

        if ($max !== null) {
            $query->where('views', '<=', $max);
        }
    }

    #[Scope]
    protected function featured(Builder $query, ?bool $featured): void
    {
        if ($featured === null) {
            return;
        }

        $query->where('featured', $featured);
    }

    #[Scope]
    protected function reaching(Builder $query, Reach $reach): void
    {
        $query->where('views', '>=', $reach->minViews());
    }

    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'featured' => 'boolean',
            'views' => 'integer',
            'rating' => 'float',
            'published_on' => 'date',
            'published_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
