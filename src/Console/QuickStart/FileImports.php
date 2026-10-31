<?php

namespace Lomkit\Rest\Console\QuickStart;

use Illuminate\Support\Str;
use PhpToken;

final readonly class FileImports
{
    public const array NAMES = [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED];

    private const string GROUP = '/^(?<prefix>[^{]*)\{(?<clauses>[^}]*)\}$/';

    /**
     * @param  array<string, string>  $classes
     * @param  list<ImportStatement>  $statements
     */
    private function __construct(public array $classes, public int $headerEnd, public array $statements) {}

    /** @param  list<PhpToken>  $source */
    public static function read(array $source): self
    {
        $code = self::code($source);
        $tokens = array_values($code);
        $offsets = array_keys($code);
        $classes = [];
        $statements = [];
        $depth = 0;

        foreach ($tokens as $index => $token) {
            $depth += match (true) {
                $token->text === '{', $token->is(T_DOLLAR_OPEN_CURLY_BRACES) => 1,
                $token->text === '}' => -1,
                default => 0,
            };

            if ($depth !== 0 || ! $token->is(T_USE) || ($tokens[$index + 1] ?? null)?->is(self::NAMES) !== true) {
                continue;
            }

            $statement = self::statement($tokens, $index + 1);
            $classes = [...$classes, ...self::clauses($statement)];
            $statements[] = ImportStatement::read($source, $offsets[$index], $offsets[$index + 1], $offsets[$index + count($statement) + 1] ?? count($source));
        }

        return new self($classes, self::headerEnd($tokens), $statements);
    }

    /**
     * @param  list<PhpToken>  $source
     * @return array<int, PhpToken>
     */
    public static function code(array $source): array
    {
        return array_filter($source, fn (PhpToken $token): bool => ! $token->isIgnorable());
    }

    public function resolve(PhpToken $name): string
    {
        if ($name->is(T_NAME_FULLY_QUALIFIED)) {
            return ltrim($name->text, '\\');
        }

        $imported = $this->find(Str::before($name->text, '\\'));

        if ($imported === null) {
            return $name->text;
        }

        return $name->is(T_NAME_QUALIFIED) ? $imported.'\\'.Str::after($name->text, '\\') : $imported;
    }

    public function nameOf(string $class): string
    {
        foreach ($this->classes as $alias => $imported) {
            if (strcasecmp($imported, $class) === 0) {
                return $alias;
            }
        }

        return $this->binds(class_basename($class)) ? '\\'.$class : class_basename($class);
    }

    public function binds(string $alias): bool
    {
        return $this->find($alias) !== null;
    }

    private function find(string $alias): ?string
    {
        return array_change_key_case($this->classes)[strtolower($alias)] ?? null;
    }

    /** @param  list<PhpToken>  $tokens */
    private static function headerEnd(array $tokens): int
    {
        $line = 1;
        $index = 0;

        while (($tokens[$index] ?? null)?->is([T_DECLARE, T_NAMESPACE]) === true) {
            $index += count(self::statement($tokens, $index));
            $line = ($tokens[$index] ?? $tokens[$index - 1])->line;
            $index++;
        }

        return $line;
    }

    /**
     * @param  list<PhpToken>  $tokens
     * @return list<PhpToken>
     */
    private static function statement(array $tokens, int $start): array
    {
        $statement = [];

        for ($index = $start; isset($tokens[$index]) && ! self::ends($tokens, $index); $index++) {
            $statement[] = $tokens[$index];
        }

        return $statement;
    }

    /** @param  list<PhpToken>  $tokens */
    private static function ends(array $tokens, int $index): bool
    {
        return $tokens[$index]->text === ';'
            || ($tokens[$index]->text === '{' && ($tokens[$index - 1] ?? null)?->is(T_NS_SEPARATOR) !== true);
    }

    /**
     * @param  list<PhpToken>  $statement
     * @return array<string, string>
     */
    private static function clauses(array $statement): array
    {
        $text = implode('', array_map(fn (PhpToken $token): string => match (true) {
            $token->is(T_AS) => ' as ',
            $token->is([T_FUNCTION, T_CONST]) => "{$token->text} ",
            default => $token->text,
        }, $statement));
        $group = preg_match(self::GROUP, $text, $match) === 1;
        $prefix = $group ? trim($match['prefix'], '\\').'\\' : '';
        $classes = [];

        foreach (explode(',', $group ? $match['clauses'] : $text) as $clause) {
            $parts = explode(' as ', trim($clause), 2);

            if ($parts[0] === '') {
                continue;
            }

            $class = $prefix.ltrim($parts[0], '\\');
            $classes[$parts[1] ?? class_basename($class)] = $class;
        }

        return $classes;
    }
}
