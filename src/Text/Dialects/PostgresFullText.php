<?php

namespace Lomkit\Rest\Text\Dialects;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Query\Expressions\TsMatch;
use Lomkit\Rest\Query\Expressions\TsRank;
use Lomkit\Rest\Text\QueryTextSearch;
use Lomkit\Rest\Text\TextCapabilities;
use Lomkit\Rest\Text\TextMode;
use Lomkit\Rest\Text\TextQuery;
use Lomkit\Rest\Text\TextTarget;
use Lomkit\Rest\Text\WebSearch;

final readonly class PostgresFullText implements QueryTextSearch
{
    /** @param non-empty-list<non-empty-list<string>> $groups */
    public function __construct(
        private array $groups,
        private string $language,
    ) {}

    #[\Override]
    public function capabilities(TextTarget $target): TextCapabilities
    {
        return TextCapabilities::of($target, TextMode::cases(), true);
    }

    #[\Override]
    public function apply(Builder $query, TextQuery $text, TextTarget $target): void
    {
        foreach ($this->qualified($target) as $columns) {
            $query->whereRaw(new TsMatch($columns, $this->language, $this->tsquery($text)), [self::value($text)], 'or');
        }
    }

    #[\Override]
    public function orderByRelevance(Builder $query, TextQuery $text, TextTarget $target, SortDirection $direction): void
    {
        $groups = $this->qualified($target);

        $query->orderBy(new TsRank($groups, $this->language, $this->tsquery($text)), $direction->value);
        $query->getQuery()->addBinding(array_fill(0, count($groups), self::value($text)), 'order');
    }

    #[\Override]
    public function run(Closure $query, TextTarget $target): mixed
    {
        return $query();
    }

    /** @return non-empty-list<non-empty-list<string>> */
    private function qualified(TextTarget $target): array
    {
        return array_map(
            fn (array $group): array => array_map(fn (string $column): string => $target->model->qualifyColumn($column), $group),
            $this->groups,
        );
    }

    private function tsquery(TextQuery $text): string
    {
        $function = match ($text->mode) {
            TextMode::Words => 'plainto_tsquery',
            TextMode::Phrase => 'phraseto_tsquery',
            TextMode::Prefix => 'to_tsquery',
            TextMode::Websearch => 'websearch_to_tsquery',
        };

        return "{$function}('{$this->language}', ?)";
    }

    private static function value(TextQuery $text): string
    {
        $terms = $text->terms();

        return match ($text->mode) {
            TextMode::Words, TextMode::Phrase => implode(' ', $terms),
            TextMode::Prefix => implode(' & ', array_map(fn (string $term): string => $term.':*', $terms)),
            TextMode::Websearch => WebSearch::parse($text->value)->toPostgres(),
        };
    }
}
