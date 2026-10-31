<?php

namespace Lomkit\Rest\Console\QuickStart;

use Illuminate\Support\Str;
use PhpToken;

final readonly class ImportStatement
{
    private function __construct(public int $start, public int $end, public string $name) {}

    /** @param  list<PhpToken>  $source */
    public static function read(array $source, int $use, int $name, int $semicolon): self
    {
        $text = implode('', array_map(fn (PhpToken $token): string => $token->text, array_slice($source, $name, $semicolon - $name)));

        return new self(self::lineStart($source, $use), self::lineEnd($source, min($semicolon, count($source) - 1)), trim($text));
    }

    public static function compare(string $first, string $second): int
    {
        return strcasecmp(self::sortKey($first), self::sortKey($second));
    }

    public function sortsAfter(string $class): bool
    {
        return self::compare($this->name, $class) > 0;
    }

    private static function sortKey(string $name): string
    {
        return str_replace(['\\', '{'], [' ', ''], $name);
    }

    /** @param  list<PhpToken>  $source */
    private static function lineStart(array $source, int $index): int
    {
        $start = $source[$index]->pos;

        while (self::opensLine($source, $index)) {
            $break = $source[$index - 1];
            $start = $source[$index]->pos - strlen(Str::afterLast($break->text, "\n"));

            if (substr_count($break->text, "\n") > 1 || ($source[$index - 2] ?? null)?->is([T_COMMENT, T_DOC_COMMENT]) !== true) {
                return $start;
            }

            $index -= 2;
        }

        return $start;
    }

    /** @param  list<PhpToken>  $source */
    private static function lineEnd(array $source, int $index): int
    {
        $last = $source[$index];

        for ($next = $index + 1; ($source[$next] ?? null)?->is([T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]) === true && ! self::breaksLine($source[$next]); $next++) {
            $last = $source[$next]->is(T_WHITESPACE) ? $last : $source[$next];
        }

        $token = isset($source[$next]) && ! self::breaksLine($source[$next]) ? $source[$index] : $last;

        return $token->pos + strlen($token->text);
    }

    /** @param  list<PhpToken>  $source */
    private static function opensLine(array $source, int $index): bool
    {
        $previous = $source[$index - 1] ?? null;

        return $previous !== null && $previous->is([T_WHITESPACE, T_OPEN_TAG]) && str_contains($previous->text, "\n");
    }

    private static function breaksLine(PhpToken $token): bool
    {
        return $token->is(T_WHITESPACE) && str_contains($token->text, "\n");
    }
}
