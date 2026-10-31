<?php

namespace Lomkit\Rest\Text;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Query\Expressions\LikeCondition;

final readonly class Contains implements TextSearch
{
    public const int MAX_WORDS = 10;

    public static function make(): self
    {
        return new self;
    }

    #[\Override]
    public function capabilities(TextTarget $target): TextCapabilities
    {
        return TextCapabilities::of($target, [TextMode::Words, TextMode::Phrase], false);
    }

    #[\Override]
    public function apply(Builder $query, TextQuery $text, TextTarget $target): void
    {
        $conditions = array_map(fn (string $column): LikeCondition => new LikeCondition($column), $target->columns());

        foreach ($this->needles($text) as $needle) {
            $pattern = LikeCondition::containing($needle);

            $query->where(function (Builder $query) use ($conditions, $pattern): void {
                foreach ($conditions as $condition) {
                    $query->whereRaw($condition, [$pattern], 'or');
                }
            });
        }
    }

    #[\Override]
    public function orderByRelevance(Builder $query, TextQuery $text, TextTarget $target, SortDirection $direction): void
    {
        throw InvalidTextSearchException::noRelevance($target->resource);
    }

    #[\Override]
    public function run(Closure $query, TextTarget $target): mixed
    {
        return $query();
    }

    /** @return list<string> */
    private function needles(TextQuery $text): array
    {
        if ($text->mode === TextMode::Phrase) {
            return [$text->phrase()];
        }

        return array_slice($text->words(), 0, self::MAX_WORDS);
    }
}
