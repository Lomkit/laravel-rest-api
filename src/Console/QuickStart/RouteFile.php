<?php

namespace Lomkit\Rest\Console\QuickStart;

use Illuminate\Support\Arr;
use Lomkit\Rest\Facades\Rest;
use PhpToken;

final readonly class RouteFile
{
    private const string GUARD = '/^->middleware\([\'"]auth:(?<guard>[^,\'"]+)[,\'"]/';

    public function __construct(public string $contents) {}

    public function registers(string $controller): bool
    {
        return $this->reference($this->code(), $controller) !== null;
    }

    public function guardOf(string $controller): ?string
    {
        $tokens = $this->code();
        $reference = $this->reference($tokens, $controller);
        $end = $reference === null ? null : self::callEnd($tokens, $reference);

        if ($end === null) {
            return null;
        }

        $chain = implode('', array_map(fn (PhpToken $token): string => $token->text, array_slice($tokens, $end + 1, 4)));

        return preg_match(self::GUARD, $chain, $match) === 1 ? $match['guard'] : null;
    }

    public function withResource(string $uri, string $controller, string $middleware): self
    {
        $imports = FileImports::read($this->source());
        $facade = $imports->nameOf(Rest::class);
        $name = $imports->nameOf($controller);
        $missing = array_filter([Rest::class => $facade, $controller => $name], fn (string $short): bool => ! $imports->binds($short) && ! str_starts_with($short, '\\'));
        $contents = $this->withImports(array_keys($missing), $imports);

        return new self(rtrim($contents)."\n\n{$facade}::resource('{$uri}', {$name}::class)\n    ->middleware('{$middleware}');\n");
    }

    /** @param  list<string>  $classes */
    private function withImports(array $classes, FileImports $imports): string
    {
        if ($classes === []) {
            return $this->contents;
        }

        usort($classes, ImportStatement::compare(...));

        if ($imports->statements === []) {
            return $this->withFirstImports($classes, $imports->headerEnd);
        }

        $last = $imports->statements[array_key_last($imports->statements)];
        $insertions = [];

        foreach ($classes as $class) {
            $next = Arr::first($imports->statements, fn (ImportStatement $statement): bool => $statement->sortsAfter($class));
            $offset = $next === null ? $last->end : $next->start;
            $insertions[$offset] = ($insertions[$offset] ?? '').($next === null ? "\nuse {$class};" : "use {$class};\n");
        }

        krsort($insertions);
        $contents = $this->contents;

        foreach ($insertions as $offset => $statements) {
            $contents = substr_replace($contents, $statements, $offset, 0);
        }

        return $contents;
    }

    /** @param  list<string>  $classes */
    private function withFirstImports(array $classes, int $line): string
    {
        $lines = explode("\n", $this->contents);

        array_splice($lines, $line, 0, [
            '',
            ...array_map(fn (string $class): string => "use {$class};", $classes),
            ...(trim($lines[$line] ?? '') === '' ? [] : ['']),
        ]);

        return implode("\n", $lines);
    }

    /** @param  list<PhpToken>  $tokens */
    private function reference(array $tokens, string $controller): ?int
    {
        $imports = FileImports::read($this->source());

        foreach ($tokens as $index => $token) {
            $isClassConstant = $token->is(FileImports::NAMES)
                && ($tokens[$index + 1] ?? null)?->is(T_DOUBLE_COLON) === true
                && ($tokens[$index + 2] ?? null)?->is(T_CLASS) === true;

            if ($isClassConstant && strcasecmp($imports->resolve($token), $controller) === 0) {
                return $index;
            }
        }

        return null;
    }

    /** @param  list<PhpToken>  $tokens */
    private static function callEnd(array $tokens, int $start): ?int
    {
        $depth = 0;

        for ($index = $start; isset($tokens[$index]); $index++) {
            $depth += match ($tokens[$index]->text) {
                '(' => 1,
                ')' => -1,
                default => 0,
            };

            if ($depth < 0) {
                return $index;
            }

            if ($depth === 0 && $tokens[$index]->text === ';') {
                return null;
            }
        }

        return null;
    }

    /** @return list<PhpToken> */
    private function code(): array
    {
        return array_values(FileImports::code($this->source()));
    }

    /** @return list<PhpToken> */
    private function source(): array
    {
        return array_values(PhpToken::tokenize($this->contents));
    }
}
