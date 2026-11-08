<?php

namespace Lomkit\Rest\Tests\Fixtures\Festival;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Unguarded]
#[UsePolicy(OpenFestivalPolicy::class)]
class Show extends Model
{
    use SoftDeletes;

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class, 'stage_code');
    }

    public function artists(): BelongsToMany
    {
        return $this->belongsToMany(Artist::class)->withPivot('role');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function labels(): MorphToMany
    {
        return $this->morphToMany(Label::class, 'labelable')->withPivot('position');
    }

    #[Scope]
    protected function startingFrom(Builder $query, string $from, int $minSeats): void
    {
        $query->where('starts_on', '>=', $from)->where('seats', '>=', $minSeats);
    }

    /** @return Attribute<string, never> */
    protected function headline(): Attribute
    {
        return Attribute::get(fn (): string => strtoupper((string) $this->title));
    }

    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return [
            'price' => 'float',
            'seats' => 'integer',
            'featured' => 'boolean',
            'starts_on' => 'date:Y-m-d',
            'starts_at' => 'datetime',
            'status' => ShowStatus::class,
            'level' => ShowLevel::class,
            'settings' => 'array',
        ];
    }
}
