<?php

namespace Lomkit\Rest\Tests\Fixtures\Newsroom;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use LogicException;

#[Unguarded]
#[UsePolicy(OpenNewsroomPolicy::class)]
class Story extends Model
{
    public static function archived(): BelongsTo
    {
        throw new LogicException('A static method is not a relation.');
    }

    public function desk(): BelongsTo
    {
        return $this->belongsTo(Desk::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Reporter::class);
    }

    public function summary(): HasOne
    {
        return $this->hasOne(Summary::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class)
            ->withPivot('position', 'note', 'pinned', 'badge', 'details')
            ->withTimestamps();
    }

    public function cover(): MorphOne
    {
        return $this->morphOne(Photo::class, 'imageable');
    }

    public function photos(): MorphMany
    {
        return $this->morphMany(Photo::class, 'imageable');
    }

    public function keywords(): MorphToMany
    {
        return $this->morphToMany(Keyword::class, 'keywordable');
    }

    public function editor()
    {
        throw new LogicException('A method without a declared relation type is not called.');
    }

    public function deskNamed(string $name): BelongsTo
    {
        throw new LogicException('A method with a required parameter is not called.');
    }

    public function headlineLength(): int
    {
        throw new LogicException('A method that does not return a relation is not called.');
    }

    protected function draftDesk(): BelongsTo
    {
        throw new LogicException('A protected method is not called.');
    }

    public function anyRelation(): ?Relation
    {
        return null;
    }
}
