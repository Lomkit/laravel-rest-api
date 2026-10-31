<?php

namespace Lomkit\Rest\Text\Dialects;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Query\Expressions\FullTextScore;
use Lomkit\Rest\Text\TermPattern;
use Lomkit\Rest\Text\TextCapabilities;
use Lomkit\Rest\Text\TextMode;
use Lomkit\Rest\Text\TextQuery;
use Lomkit\Rest\Text\TextSearch;
use Lomkit\Rest\Text\TextTarget;
use Lomkit\Rest\Text\WebSearch;

final readonly class MySqlFullText implements TextSearch
{
    private const int MISSING_INDEX = 1191;

    /** @param non-empty-list<non-empty-list<string>> $groups */
    public function __construct(private array $groups) {}

    #[\Override]
    public function capabilities(TextTarget $target): TextCapabilities
    {
        return TextCapabilities::of($target, TextMode::cases(), true);
    }

    #[\Override]
    public function apply(Builder $query, TextQuery $text, TextTarget $target): void
    {
        $against = self::against($text);
        $options = self::isBoolean($text) ? ['mode' => 'boolean'] : [];

        foreach ($this->groups as $group) {
            $query->orWhereFullText(self::qualified($target, $group), $against, $options);
        }
    }

    #[\Override]
    public function orderByRelevance(Builder $query, TextQuery $text, TextTarget $target, SortDirection $direction): void
    {
        $groups = array_map(fn (array $group): array => self::qualified($target, $group), $this->groups);

        $query->orderBy(new FullTextScore($groups, self::isBoolean($text)), $direction->value);
        $query->getQuery()->addBinding(array_fill(0, count($groups), self::against($text)), 'order');
    }

    #[\Override]
    public function run(Closure $query, TextTarget $target): mixed
    {
        try {
            return $query();
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) !== self::MISSING_INDEX) {
                throw $exception;
            }

            throw InvalidTextSearchException::missingFullTextIndex($target->table(), $this->unindexed($target), $exception);
        }
    }

    /** @return list<non-empty-list<string>> */
    private function unindexed(TextTarget $target): array
    {
        $indexed = [];

        $indexes = $target->model
            ->getConnection()
            ->getSchemaBuilder()
            ->getIndexes($target->table());

        foreach ($indexes as $index) {
            if ($index['type'] === 'fulltext') {
                $indexed[] = self::sorted($index['columns']);
            }
        }

        $missing = array_filter($this->groups, fn (array $group): bool => ! in_array(self::sorted($group), $indexed, true));

        return $missing === [] ? $this->groups : array_values($missing);
    }

    private static function against(TextQuery $text): string
    {
        $terms = $text->terms(TermPattern::Underscored);

        return match ($text->mode) {
            TextMode::Words => implode(' ', $terms),
            TextMode::Phrase => '+"'.implode(' ', $terms).'"',
            TextMode::Prefix => implode(' ', array_map(fn (string $term): string => '+'.$term.'*', $terms)),
            TextMode::Websearch => WebSearch::parse($text->value, TermPattern::Underscored)->toMySqlBoolean(),
        };
    }

    private static function isBoolean(TextQuery $text): bool
    {
        return $text->mode !== TextMode::Words;
    }

    /**
     * @param  list<string>  $columns
     * @return list<string>
     */
    private static function sorted(array $columns): array
    {
        sort($columns);

        return $columns;
    }

    /**
     * @param  non-empty-list<string>  $group
     * @return non-empty-list<string>
     */
    private static function qualified(TextTarget $target, array $group): array
    {
        return array_map(fn (string $column): string => $target->model->qualifyColumn($column), $group);
    }
}
