<?php

namespace Lomkit\Rest\Text;

final readonly class WebSearch
{
    private const string SYNTAX = '/(-?)(?:"([^"]*)"?|([^\s"]+))/u';

    private const string ALTERNATIVE = 'or';

    /**
     * @param  list<non-empty-list<non-empty-list<string>>>  $groups
     * @param  list<non-empty-list<string>>  $excluded
     */
    private function __construct(
        public array $groups,
        public array $excluded,
    ) {}

    public static function parse(string $value, TermPattern $pattern = TermPattern::Alphanumeric): self
    {
        preg_match_all(self::SYNTAX, $value, $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);

        $groups = [];
        $excluded = [];
        $alternative = false;

        foreach ($matches as $match) {
            $negated = $match[1] === '-';
            $quoted = $match[2] !== null;
            $raw = $match[2] ?? $match[3] ?? '';

            if (! $negated && ! $quoted && self::isAlternative($raw)) {
                $alternative = $groups !== [];

                continue;
            }

            $terms = $pattern->terms($raw);

            if ($terms === []) {
                continue;
            }

            if ($negated) {
                $excluded[] = $terms;
            } elseif ($alternative) {
                $groups[array_key_last($groups)][] = $terms;
            } else {
                $groups[] = [$terms];
            }

            $alternative = false;
        }

        return new self($groups, $excluded);
    }

    public function toPostgres(): string
    {
        $groups = array_map(fn (array $group): string => implode(' '.self::ALTERNATIVE.' ', array_map(self::postgresTerm(...), $group)), $this->groups);
        $excluded = array_map(fn (array $terms): string => '-'.self::postgresTerm($terms), $this->excluded);

        return implode(' ', [...$groups, ...$excluded]);
    }

    public function toMySqlBoolean(): string
    {
        $groups = array_map(fn (array $group): string => count($group) === 1
            ? '+'.self::term($group[0])
            : '+('.implode(' ', array_map(self::term(...), $group)).')', $this->groups);
        $excluded = array_map(fn (array $terms): string => '-'.self::term($terms), $this->excluded);

        return implode(' ', [...$groups, ...$excluded]);
    }

    private static function isAlternative(string $word): bool
    {
        return strtolower($word) === self::ALTERNATIVE;
    }

    /** @param non-empty-list<string> $terms */
    private static function postgresTerm(array $terms): string
    {
        return count($terms) === 1 && self::isAlternative($terms[0]) ? '"'.$terms[0].'"' : self::term($terms);
    }

    /** @param non-empty-list<string> $terms */
    private static function term(array $terms): string
    {
        return count($terms) === 1 ? $terms[0] : '"'.implode(' ', $terms).'"';
    }
}
