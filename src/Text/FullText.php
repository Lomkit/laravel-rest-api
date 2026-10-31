<?php

namespace Lomkit\Rest\Text;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Fields\SortDirection;
use Lomkit\Rest\Text\Dialects\MySqlFullText;
use Lomkit\Rest\Text\Dialects\PostgresFullText;

final readonly class FullText implements QueryTextSearch
{
    private const string CONFIGURATION_NAME = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    /** @param list<non-empty-list<string>> $groups */
    private function __construct(
        private array $groups = [],
        private string $language = 'english',
        private ?QueryTextSearch $otherwise = null,
    ) {}

    public static function make(): self
    {
        return new self;
    }

    public function index(string ...$columns): self
    {
        if ($columns === []) {
            throw InvalidTextSearchException::emptyIndex();
        }

        return new self([...$this->groups, array_values($columns)], $this->language, $this->otherwise);
    }

    public function language(string $language): self
    {
        if (preg_match(self::CONFIGURATION_NAME, $language) !== 1) {
            throw InvalidTextSearchException::invalidLanguage($language);
        }

        return new self($this->groups, $language, $this->otherwise);
    }

    public function otherwise(QueryTextSearch $strategy): self
    {
        return new self($this->groups, $this->language, $strategy);
    }

    #[\Override]
    public function capabilities(TextTarget $target): TextCapabilities
    {
        return $this->resolve($target)->capabilities($target);
    }

    #[\Override]
    public function apply(Builder $query, TextQuery $text, TextTarget $target): void
    {
        $this->resolve($target)->apply($query, $text, $target);
    }

    #[\Override]
    public function orderByRelevance(Builder $query, TextQuery $text, TextTarget $target, SortDirection $direction): void
    {
        $this->resolve($target)->orderByRelevance($query, $text, $target, $direction);
    }

    #[\Override]
    public function run(Closure $query, TextTarget $target): mixed
    {
        return $this->resolve($target)->run($query, $target);
    }

    private function resolve(TextTarget $target): QueryTextSearch
    {
        $groups = $this->groups($target);

        return match ($target->driver()) {
            'mysql', 'mariadb' => new MySqlFullText($groups),
            'pgsql' => new PostgresFullText($groups, $this->language),
            default => $this->otherwise ?? throw InvalidTextSearchException::unsupportedDriver($target->resource, $target->driver()),
        };
    }

    /** @return non-empty-list<non-empty-list<string>> */
    private function groups(TextTarget $target): array
    {
        $searchable = $target->names();

        foreach ($this->groups as $group) {
            foreach ($group as $column) {
                if (! in_array($column, $searchable, true)) {
                    throw InvalidTextSearchException::notSearchable($target->resource, $column);
                }
            }
        }

        return $this->groups === [] ? [$searchable] : $this->groups;
    }
}
